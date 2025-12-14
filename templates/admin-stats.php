<?php
/**
 * Template para a páxina de estatísticas do admin
 *
 * @package Postais_Nadal
 */

// Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$stats = isset( $stats ) ? $stats : array();
$postals = isset( $postals ) ? $postals : array();
$current_page = isset( $current_page ) ? $current_page : 1;
$total_pages = isset( $total_pages ) ? $total_pages : 1;
$per_page = isset( $per_page ) ? $per_page : 20;
$total = isset( $total ) ? $total : 0;
?>

<div class="wrap postais-nadal-stats">
	<h1>Postais de Nadal - Estatísticas</h1>

	<!-- Dashboard de Estatísticas -->
	<div class="postais-stats-dashboard">
		<div class="postais-stat-card">
			<div class="postais-stat-number"><?php echo esc_html( $stats['total'] ); ?></div>
			<div class="postais-stat-label">Postais Totais</div>
		</div>

		<div class="postais-stat-card">
			<div class="postais-stat-number"><?php echo esc_html( $stats['unique_emails'] ); ?></div>
			<div class="postais-stat-label">Usuarios Únicos</div>
		</div>

		<div class="postais-stat-card">
			<div class="postais-stat-number"><?php echo esc_html( isset( $stats['today'] ) ? $stats['today'] : 0 ); ?></div>
			<div class="postais-stat-label">Postais Hoxe</div>
		</div>
	</div>

	<!-- Gráfico última semana -->
	<?php if ( ! empty( $stats['last_week'] ) ) : ?>
		<div class="postais-section">
			<h2>Última Semana</h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>Data</th>
						<th>Postais Xeradas</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $stats['last_week'] as $day ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( 'd/m/Y', strtotime( $day['date'] ) ) ); ?></td>
							<td><?php echo esc_html( $day['count'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

	<!-- Táboa de Postais -->
	<div class="postais-section">
		<h2>Postais Xeradas</h2>

		<?php if ( ! empty( $postals ) ) : ?>
			<!-- Controis superiores -->
			<div class="tablenav top">
				<div class="alignleft actions bulkactions">
					<button type="button" id="postais-delete-selected" class="button" disabled>
						<span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
						Borrar seleccionadas
					</button>
				</div>
				<div class="alignright">
					<form method="get" style="display: inline-flex; align-items: center; gap: 8px;">
						<input type="hidden" name="page" value="postais-nadal-stats">
						<label for="per_page">Mostrar:</label>
						<select name="per_page" id="per_page" onchange="this.form.submit()">
							<option value="20" <?php selected( $per_page, 20 ); ?>>20</option>
							<option value="40" <?php selected( $per_page, 40 ); ?>>40</option>
						</select>
						<span>de <?php echo esc_html( $total ); ?> postais</span>
					</form>
				</div>
				<br class="clear">
			</div>

			<table class="wp-list-table widefat fixed striped postais-table">
				<thead>
					<tr>
						<th class="column-cb check-column">
							<input type="checkbox" id="postais-select-all">
						</th>
						<th class="column-id">ID</th>
						<th class="column-email">Email</th>
						<th class="column-ip">IP</th>
						<th class="column-date">Data</th>
						<th class="column-thumbnail">Miniatura</th>
						<th class="column-actions">Accións</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $postals as $postal ) : ?>
						<tr data-id="<?php echo esc_attr( $postal['id'] ); ?>">
							<td class="check-column">
								<input type="checkbox" class="postais-checkbox" value="<?php echo esc_attr( $postal['id'] ); ?>">
							</td>
							<td><?php echo esc_html( $postal['id'] ); ?></td>
							<td><?php echo esc_html( $postal['email'] ); ?></td>
							<td><?php echo esc_html( $postal['ip'] ); ?></td>
							<td><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $postal['timestamp'] ) ) ); ?></td>
							<td>
								<?php if ( ! empty( $postal['image_url'] ) ) : ?>
									<img src="<?php echo esc_url( $postal['image_url'] ); ?>" alt="Postal" class="postais-thumbnail">
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $postal['image_url'] ) ) : ?>
									<a href="<?php echo esc_url( $postal['image_url'] ); ?>" target="_blank" class="button button-small">
										Ver
									</a>
								<?php endif; ?>
								<button type="button" class="button button-small button-link-delete postais-delete-single" data-id="<?php echo esc_attr( $postal['id'] ); ?>">
									Borrar
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<!-- Paxinación -->
			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						$pagination_args = array(
							'base' => add_query_arg( array( 'paged' => '%#%', 'per_page' => $per_page ) ),
							'format' => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total' => $total_pages,
							'current' => $current_page,
						);
						echo paginate_links( $pagination_args );
						?>
					</div>
				</div>
			<?php endif; ?>

		<?php else : ?>
			<p>Aínda non se xerou ningunha postal.</p>
		<?php endif; ?>
	</div>
</div>

<!-- Lightbox simple -->
<div id="postais-lightbox" class="postais-lightbox" style="display:none;">
	<div class="postais-lightbox-overlay"></div>
	<div class="postais-lightbox-content">
		<button class="postais-lightbox-close" aria-label="Pechar">&times;</button>
		<img src="" alt="Postal ampliada">
	</div>
</div>
