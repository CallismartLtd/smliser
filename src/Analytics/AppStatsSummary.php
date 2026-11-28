<?php
/**
 * App stats summary class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Analytics
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Analytics;

use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\Utils\Format;

/**
 * An app's download and client access figures, as the dashboards show them.
 *
 * The admin dashboard's app page draws them as a chart with stat cards; the
 * client dashboard shows the same cards for the owner's apps. Both read them
 * here, so the figures and their labels are the same in both.
 */
final class AppStatsSummary {

	/**
	 * Days the averages, growth and timelines cover.
	 */
	public const DAYS = 30;

	/**
	 * @param AppsAnalytics $analytics
	 */
	public function __construct( private AppsAnalytics $analytics ) {}

	/**
	 * The figures of an app.
	 *
	 * @param AbstractHostedApp $app
	 * @return array{
	 *     downloads: array{total: int, today: int, average: float, growth: float, peak_day: mixed,
	 *         timeline: array{days: list<string>, values: list<int>}},
	 *     client_access: array{total: int, average: float, growth: float, peak_day: mixed, active_installs: int,
	 *         timeline: array{days: list<string>, values: list<int>}}
	 * }
	 */
	public function analytics( AbstractHostedApp $app ) : array {
		$downloads_daily = $this->analytics->get_downloads_per_day( $app, self::DAYS );
		ksort( $downloads_daily );

		$access_daily = $this->analytics->get_client_access_per_day( $app, self::DAYS );
		ksort( $access_daily );

		return [
			'downloads'     => [
				'total'    => $this->analytics->get_total_downloads( $app ),
				'today'    => $this->analytics->get_todays_downloads( $app ),
				'average'  => $this->analytics->get_average_daily_downloads( $app, self::DAYS ),
				'growth'   => $this->analytics->get_download_growth_percentage( $app, self::DAYS ),
				'peak_day' => $this->analytics->get_peak_download_day( $app, 365 ),
				'timeline' => [
					'days'   => array_keys( $downloads_daily ),
					'values' => array_values( $downloads_daily ),
				],
			],
			'client_access' => [
				'total'           => $this->analytics->get_total_client_accesses( $app ),
				'average'         => $this->analytics->get_average_daily_client_accesses( $app, self::DAYS ),
				'growth'          => $this->analytics->get_client_access_growth_percentage( $app, self::DAYS ),
				'peak_day'        => $this->analytics->get_peak_client_access_day( $app, 365 ),
				'active_installs' => $this->analytics->get_estimated_active_installations( $app, self::DAYS ),
				'timeline'        => [
					'days'   => array_keys( $access_daily ),
					'values' => array_values( $access_daily ),
				],
			],
		];
	}

	/**
	 * The stat cards of an app's figures.
	 *
	 * @param array $analytics The figures, from analytics().
	 * @return list<array{icon: string, label: string, value: string, color: string, subtitle?: string, highlight?: bool}>
	 */
	public static function stats( array $analytics ) : array {
		$stats = [];

		if ( ! empty( $analytics['downloads'] ) ) {
			$downloads = $analytics['downloads'];

			if ( isset( $downloads['total'] ) ) {
				$stats[] = [
					'icon'  => 'ti ti-download',
					'label' => smliser__( 'Total Downloads' ),
					'value' => Format::number( $downloads['total'] ),
					'color' => '#3b82f6',
				];
			}

			if ( isset( $downloads['today'] ) ) {
				$stats[] = [
					'icon'  => 'ti ti-chart-arrows',
					'label' => smliser__( 'Today\'s Downloads' ),
					'value' => Format::number( $downloads['today'] ),
					'color' => '#3b82f6',
				];
			}

			if ( isset( $downloads['average'] ) ) {
				$stats[] = [
					'icon'  => 'ti ti-chart-infographic',
					'label' => smliser__( 'Avg Downloads/Day' ),
					'value' => Format::number( $downloads['average'], 1 ),
					'color' => '#3b82f6',
				];
			}

			if ( isset( $downloads['growth'] ) ) {
				$growth  = (float) $downloads['growth'];
				$stats[] = [
					'icon'      => $growth >= 0 ? 'ti ti-chart-dots-2' : 'ti ti-chart-line',
					'label'     => smliser__( 'Download Growth' ),
					'value'     => ( $growth >= 0 ? '+' : '' ) . Format::number( $growth, 1 ) . '%',
					'color'     => $growth >= 0 ? '#10b981' : '#ef4444',
					'highlight' => true,
				];
			}

			if ( ! empty( $downloads['peak_day'] ) && isset( $downloads['peak_day']['count'] ) ) {
				$stats[] = [
					'icon'     => 'ti ti-trophy',
					'label'    => smliser__( 'Peak Day' ),
					'value'    => Format::number( $downloads['peak_day']['count'] ),
					'subtitle' => date( 'M j, Y', strtotime( $downloads['peak_day']['date'] ) ),
					'color'    => '#8b5cf6',
				];
			}
		}

		if ( ! empty( $analytics['client_access'] ) ) {
			$access = $analytics['client_access'];

			if ( isset( $access['total'] ) ) {
				$stats[] = [
					'icon'  => 'ti ti-world-www',
					'label' => smliser__( 'Total Client Access' ),
					'value' => Format::number( $access['total'] ),
					'color' => '#10b981',
				];
			}

			if ( isset( $access['average'] ) ) {
				$stats[] = [
					'icon'  => 'ti ti-percentage-33',
					'label' => smliser__( 'Avg Access/Day' ),
					'value' => Format::number( $access['average'], 1 ),
					'color' => '#10b981',
				];
			}

			if ( isset( $access['growth'] ) ) {
				$growth  = (float) $access['growth'];
				$stats[] = [
					'icon'      => $growth >= 0 ? 'ti ti-chart-dots-2' : 'ti ti-chart-line',
					'label'     => smliser__( 'Access Growth' ),
					'value'     => ( $growth >= 0 ? '+' : '' ) . Format::number( $growth, 1 ) . '%',
					'color'     => $growth >= 0 ? '#10b981' : '#ef4444',
					'highlight' => true,
				];
			}

			if ( isset( $access['active_installs'] ) ) {
				$stats[] = [
					'icon'      => 'ti ti-bolt',
					'label'     => smliser__( 'Active Installs' ),
					'value'     => '~' . Format::number( $access['active_installs'] ),
					'color'     => '#f59e0b',
					'highlight' => true,
				];
			}

			if ( ! empty( $access['peak_day'] ) && isset( $access['peak_day']['count'] ) ) {
				$stats[] = [
					'icon'     => 'ti ti-trophy',
					'label'    => smliser__( 'Peak Access Day' ),
					'value'    => Format::number( $access['peak_day']['count'] ),
					'subtitle' => date( 'M j, Y', strtotime( $access['peak_day']['date'] ) ),
					'color'    => '#8b5cf6',
				];
			}
		}

		return $stats;
	}
}
