<?php
/**
 * Licensing Service class file
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @subpackage Monetization
 */

namespace SmartLicenseServer\Monetization;

use DateTimeImmutable;
use DateTimeZone;
use SmartLicenseServer\Analytics\RepositoryAnalytics;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\Security\Actors\User;

/**
 * The Licensing API: how licenses are issued and changed.
 *
 * Smart License Server never collects money and never manages orders or
 * subscriptions. A provider sells; when a sale is confirmed, the provider
 * asks this API to issue a license from a pricing tier, and later to extend
 * it, change its status or its terms. Every provider uses this same API,
 * built in or from an extension; the REST Licensing API calls it too.
 *
 * Rules:
 *  - A license is issued from an active pricing tier, whose terms are
 *    copied onto it (PricingTier::to_license_contract()).
 *  - A provider can only issue from a tier it sells. An empty provider ID
 *    means an administrator issues the license; any active tier can be used.
 *  - A provider and its reference for the sale identify one license:
 *    issuing again with the same pair returns the license already issued,
 *    so a repeated request never issues twice.
 *  - The licensee email is required: the license is delivered to it.
 *
 * Methods do not throw: they return the license or true, or an Exception
 * whose data has an HTTP "status".
 */
class LicensingService {

	/**
	 * @param RepositoryAnalytics|null $analytics Records license activity (site removals,
	 *                                            status changes); nothing is recorded without it.
	 */
	public function __construct(
		protected ?RepositoryAnalytics $analytics = null
	) {}

	/**
	 * Issue a license from a pricing tier.
	 *
	 * @param PricingTier             $tier           The tier the license is issued from.
	 * @param string                  $licensee_email Where the license is delivered.
	 * @param string                  $licensee_name  The licensee's name, as the provider has it.
	 * @param string                  $provider_id    The provider that sold it; empty when an administrator issues it.
	 * @param string                  $external_ref   The provider's reference for the sale; empty for none.
	 * @param string                  $service_id     The ID a client activates with; generated when empty.
	 * @param DateTimeImmutable|null  $starts_at      When the license starts; now when null.
	 * @return License|Exception The license (the one already issued, for a repeated
	 *                           provider reference), or why it was not issued.
	 */
	public function issue(
		PricingTier $tier,
		string $licensee_email,
		string $licensee_name = '',
		string $provider_id = '',
		string $external_ref = '',
		string $service_id = '',
		?DateTimeImmutable $starts_at = null
	) : License|Exception {
		if ( '' !== $external_ref && '' === $provider_id ) {
			return new Exception( 'invalid_source', smliser__( 'A provider reference needs a provider ID.' ), [ 'status' => 400 ] );
		}

		$existing = License::get_by_source( $provider_id, $external_ref );

		if ( $existing ) {
			return $existing;
		}

		if ( ! $tier->exists() ) {
			return new Exception( 'tier_not_found', smliser__( 'The pricing tier does not exist.' ), [ 'status' => 404 ] );
		}

		if ( ! $tier->is_active() ) {
			return new Exception( 'tier_archived', sprintf( smliser__( 'The pricing tier "%s" is archived and issues no new licenses.' ), $tier->get_name() ), [ 'status' => 409 ] );
		}

		if ( '' !== $provider_id && $provider_id !== $tier->get_provider_id() ) {
			return new Exception( 'tier_not_sold_by_provider', sprintf( smliser__( 'The pricing tier "%s" is not sold by the provider "%s".' ), $tier->get_name(), $provider_id ), [ 'status' => 403 ] );
		}

		$app = Monetization::get_by_id( $tier->get_monetization_id() )?->get_app();

		if ( ! ( $app instanceof AbstractHostedApp ) ) {
			return new Exception( 'app_not_found', smliser__( 'The app this pricing tier belongs to does not exist.' ), [ 'status' => 404 ] );
		}

		$license = new License();

		try {
			$license->set_licensee_email( $licensee_email );
		} catch ( \InvalidArgumentException $e ) {
			return new Exception( 'invalid_email', $e->getMessage(), [ 'status' => 400 ] );
		}

		if ( '' === $license->get_licensee_email() ) {
			return new Exception( 'missing_email', smliser__( 'The licensee email is required.' ), [ 'status' => 400 ] );
		}

		$contract = $tier->to_license_contract( $starts_at ?? new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) );

		// A verified account owns its address, so the license is theirs from the start.
		$owner = User::get_by_email( $license->get_licensee_email() );

		if ( $owner && $owner->is_email_verified() ) {
			$license->set_user_id( $owner->get_id() );
		}

		$license->set_app( $app )
			->set_licensee_fullname( $licensee_name )
			->set_service_id( $service_id )
			->set_tier_id( $tier->get_id() )
			->set_source( $provider_id, $external_ref )
			->set_max_allowed_domains( $contract['max_allowed_domains'] )
			->set_start_date( $contract['start_date'] )
			->set_end_date( $contract['end_date'] )
			->set_features( $contract['features'] );

		if ( ! $license->save() ) {
			// Another request may have issued it under the same reference first.
			return License::get_by_source( $provider_id, $external_ref )
				?? new Exception( 'license_not_saved', smliser__( 'The license could not be saved.' ), [ 'status' => 500 ] );
		}

		return $license;
	}

	/**
	 * Move the end of a license, e.g. after a renewal.
	 *
	 * An expired license whose new end is in the future gets its status
	 * calculated from its dates again.
	 *
	 * @param License                $license The license.
	 * @param DateTimeImmutable|null $ends_at The new end; null for no end (lifetime).
	 * @return true|Exception
	 */
	public function extend( License $license, ?DateTimeImmutable $ends_at ) : true|Exception {
		if ( ! $license->exists() ) {
			return new Exception( 'license_not_found', smliser__( 'The license does not exist.' ), [ 'status' => 404 ] );
		}

		$license->set_end_date( $ends_at );

		if ( License::STATUS_EXPIRED === $license->get_status( 'edit' ) && ( null === $ends_at || $ends_at->getTimestamp() > time() ) ) {
			$license->set_status( '' );
		}

		return $this->save( $license );
	}

	/**
	 * Set the status of a license.
	 *
	 * @param License $license The license.
	 * @param string  $status  One of License::get_allowed_statuses(), or empty to
	 *                         calculate the status from the license dates.
	 * @param array{ip_address?: string, user_agent?: string, comment?: string, duration?: float}|null $activity
	 *                         Request details to record the change in the license activity
	 *                         log with; null to change it without recording.
	 * @return true|Exception
	 */
	public function set_status( License $license, string $status, ?array $activity = null ) : true|Exception {
		if ( ! $license->exists() ) {
			return new Exception( 'license_not_found', smliser__( 'The license does not exist.' ), [ 'status' => 404 ] );
		}

		if ( '' !== $status && ! in_array( $status, License::get_allowed_statuses(), true ) ) {
			return new Exception( 'invalid_status', sprintf( smliser__( 'Unknown license status "%s". Use one of: %s.' ), $status, implode( ', ', License::get_allowed_statuses() ) ), [ 'status' => 400 ] );
		}

		$previous = $license->get_status();

		$license->set_status( $status );

		$saved = $this->save( $license );

		if ( true === $saved && null !== $activity ) {
			$this->log( $license, 'status_change', $activity + [
				'comment' => sprintf( 'Status changed from %s to %s', $previous, $license->get_status() ),
			] );
		}

		return $saved;
	}

	/**
	 * Free a site: remove a domain from the domains a license is activated on.
	 *
	 * The domain's download tokens are revoked with it, so the freed site
	 * keeps no download access, and the removal is recorded in the license
	 * activity log as an uninstallation, the way the REST uninstallation
	 * records it. Who may do this is the caller's check: the admin
	 * dashboard (Monetization\Controller) and the app owner's client
	 * dashboard (ClientDashboard\ActionHandlers\AppLicenseActions).
	 *
	 * @param License $license  The license.
	 * @param string  $domain   The domain or URL, as activated.
	 * @param array{ip_address?: string, user_agent?: string, comment?: string, duration?: float} $activity
	 *                          Request details for the activity log.
	 * @return true|Exception
	 */
	public function free_site( License $license, string $domain, array $activity = [] ) : true|Exception {
		if ( ! $license->exists() ) {
			return new Exception( 'license_not_found', smliser__( 'The license does not exist.' ), [ 'status' => 404 ] );
		}

		$domain = trim( $domain );

		if ( '' === $domain ) {
			return new Exception( 'invalid_input', smliser__( 'Provide the domain to uninstall.' ), [ 'status' => 400 ] );
		}

		if ( $license->is_new_domain( $domain ) ) {
			return new Exception( 'domain_not_active', sprintf( smliser__( 'The license is not activated on %s.' ), $domain ), [ 'status' => 404 ] );
		}

		$host = License::activation_host( $domain );

		if ( ! $license->remove_activated_domain( $domain ) ) {
			return new Exception( 'site_not_freed', sprintf( smliser__( 'Unable to uninstall "%s"' ), $domain ), [ 'status' => 500 ] );
		}

		// A freed domain keeps no download access.
		DownloadToken::revoke_for_activation( $license, $host );

		$this->log( $license, 'uninstallation', $activity + [
			'website' => $host,
			'comment' => sprintf( 'The domain "%s" was removed from the license', $host ),
		] );

		return true;
	}

	/**
	 * Change the terms of a license.
	 *
	 * @param License $license The license.
	 * @param array{
	 *  max_allowed_domains?: int,
	 *  end_date?: DateTimeImmutable|null,
	 *  features?: list<string>
	 * } $terms The terms to change; terms not given are kept.
	 * @return true|Exception
	 */
	public function update_contract( License $license, array $terms ) : true|Exception {
		if ( ! $license->exists() ) {
			return new Exception( 'license_not_found', smliser__( 'The license does not exist.' ), [ 'status' => 404 ] );
		}

		$unknown = array_diff( array_keys( $terms ), [ 'max_allowed_domains', 'end_date', 'features' ] );

		if ( ! empty( $unknown ) ) {
			return new Exception( 'invalid_terms', sprintf( smliser__( 'Unknown license terms: %s.' ), implode( ', ', $unknown ) ), [ 'status' => 400 ] );
		}

		if ( array_key_exists( 'max_allowed_domains', $terms ) ) {
			$license->set_max_allowed_domains( (int) $terms['max_allowed_domains'] );
		}

		if ( array_key_exists( 'end_date', $terms ) ) {
			$license->set_end_date( $terms['end_date'] );
		}

		if ( array_key_exists( 'features', $terms ) ) {
			$license->set_features( (array) $terms['features'] );
		}

		return $this->save( $license );
	}

	/**
	 * Find the license a provider sold under the given reference.
	 *
	 * @param string $provider_id  The provider ID.
	 * @param string $external_ref The provider's reference for the sale.
	 * @return License|null
	 */
	public function find_by_reference( string $provider_id, string $external_ref ) : ?License {
		return License::get_by_source( $provider_id, $external_ref );
	}

	/**
	 * Record a license activity, when an activity log is available.
	 *
	 * @param License              $license
	 * @param string               $event_type activation, deactivation, uninstallation, status_change, ...
	 * @param array<string, mixed> $activity   ip_address, user_agent, website, comment, duration.
	 * @return void
	 */
	protected function log( License $license, string $event_type, array $activity ) : void {
		$this->analytics?->log_license_activity( [
			'license_id' => $license->get_id(),
			'event_type' => $event_type,
		] + $activity );
	}

	/**
	 * Save a changed license.
	 *
	 * @param License $license
	 * @return true|Exception
	 */
	protected function save( License $license ) : true|Exception {
		return $license->save()
			? true
			: new Exception( 'license_not_saved', smliser__( 'The license could not be saved.' ), [ 'status' => 500 ] );
	}
}
