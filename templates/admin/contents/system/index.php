<?php
/**
 * The admin system schedules page template.
 *
 * @author Callistus Nwachukwu
 * @var array<string, array{task: \SmartLicenseServer\Background\Schedule\ScheduledTask, state: array{last_ran_at: \DateTimeImmutable|null, next_run_at: \DateTimeImmutable|null, last_error: string|null}}> $tasks
 * @var SmartLicenseServer\Admin\ContentHandlers\ToolsPage $page_handler
 * @var SmartLicenseServer\Core\Request $request
 */

/**
 * JSON-encode a value for safe embedding in a data-* attribute.
 *
 * @param mixed $value
 * @return string
 */
$encode_for_modal = static function ( mixed $value ) : string {
	if ( null === $value ) {
		return '';
	}

	$text = is_scalar( $value ) ? (string) $value : smliser_safe_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

	return $text ?? '';
};

$menu_args = $page_handler->get_top_menu_args( $request );

?>
<div class="smliser-admin-page">
    <?php smliser_print_admin_content_header( $menu_args ); ?>

    <div class="smliser-table-wrapper">

        <div class="smliser-tools-actions" id="smliser-background-actions">
            <span class="smliser-section-description">
                <i class="ti ti-info-circle"></i>
                <?php smliser_esc_html_e( 'Tasks normally run from cron (`schedule run` every minute). Use these buttons to run them now; nothing runs while the application is being updated.' ); ?>
            </span>
            <button
                type="button"
                class="smliser-btn smliser-btn-glass"
                data-bg-action="schedule/run"
                data-confirm="<?php smliser_esc_attr_e( 'Run every task that is due now? Each runs in this request.' ); ?>"
            ><i class="ti ti-player-play"></i> <?php smliser_esc_html_e( 'Run due tasks' ); ?></button>

        </div>
        <table class="smliser-table widefat striped">
            <thead class="<?php echo empty( $tasks ) ? 'smliser-hide': ''; ?>">
                <tr>
                    <th><?php smliser_esc_html_e( 'Task ID' ); ?></th>
                    <th><?php smliser_esc_html_e( 'Label' ); ?></th>
                    <th><?php smliser_esc_html_e( 'Last Ran' ); ?></th>
                    <th><?php smliser_esc_html_e( 'Next Run' ); ?></th>
                    <th><?php smliser_esc_html_e( 'State' ); ?></th>
                    <th><?php smliser_esc_html_e( 'Action' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $tasks ) ) : ?>
                    <tr>
                        <td class="empty-state" colspan="6"><?php smliser_esc_html_e( 'No scheduled tasks registered.' ); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ( $tasks as $data ) :
                    	$task  = $data['task'];
                    	$state = $data['state'];

                    	$last_ran = $state['last_ran_at'] ? $state['last_ran_at']->format( \smliser_datetime_format() ) : 'Never';
                    	$next_run = $state['next_run_at'] ? $state['next_run_at']->format( \smliser_datetime_format() ) : 'N/A';
                    	$status   = $task->is_due( $state['last_ran_at'] ) ? 'DUE' : 'WAITING';

                    	$status = $state['last_error'] ? 'ERROR' : $status;
                    	?>
                        <tr >
                            <td><?php echo escHtml( $task->get_id() ) ?></td>
                            <td><?php echo escHtml( $task->get_label() ) ?></td>
                            <td><?php echo escHtml( $last_ran ); ?></td>
                            <td><?php echo escHtml( $next_run ); ?></td>
                            <td><?php echo escHtml( $status ); ?></td>
                            <td>
                                <button
                                    type="button"
                                    class="smliser-btn-glass"
                                    data-bg-action="schedule/run"
                                    data-task-id="<?php echo escAttr( $task->get_id() ); ?>"
                                    data-confirm="<?php echo escAttr( sprintf( smliser__( 'Run "%s" now?' ), $task->get_label() ) ); ?>"
                                    title="<?php smliser_esc_attr_e( 'Run this task now, whether or not it is due' ); ?>"
                                ><i class="ti ti-player-play"></i> <?php smliser_esc_html_e( 'Run now' ); ?></button>

                                <?php if ( 'ERROR' === $status ) : ?>
                                    <button
                                        type="button"
                                        class="smliser-btn-glass smliser-view-detail"
                                        data-title="Error &mdash; <?php echo escAttr( (string) $task->get_label() ); ?>"
                                        data-content="<?php echo escAttr( $encode_for_modal( $state['last_error'] ) ); ?>"
                                    ><?php smliser_esc_html_e( 'View error' ); ?></button>
                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>