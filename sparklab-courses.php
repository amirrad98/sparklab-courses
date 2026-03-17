<?php
/**
 * Plugin Name: SparkLab Courses
 * Description: Course & certification system for SparkLab's 3D fabrication training.
 * Version: 1.0.0
 * Author: SparkLab
 * Text Domain: sparklab-courses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SPARKLAB_COURSES_VERSION', '1.0.0' );
define( 'SPARKLAB_COURSES_URL', plugin_dir_url( __FILE__ ) );
define( 'SPARKLAB_COURSES_PATH', plugin_dir_path( __FILE__ ) );

/* ──────────────────────────────────────────────────
   Custom Post Type — hierarchical (parent = course, child = module)
   ────────────────────────────────────────────────── */

function sparklab_courses_register_cpt() {
	register_post_type( 'sparklab_course', array(
		'labels' => array(
			'name'               => 'Courses',
			'singular_name'      => 'Course',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New Course / Module',
			'edit_item'          => 'Edit Course / Module',
			'new_item'           => 'New Course',
			'view_item'          => 'View Course',
			'search_items'       => 'Search Courses',
			'not_found'          => 'No courses found',
			'not_found_in_trash' => 'No courses found in Trash',
			'parent_item_colon'  => 'Parent Course:',
			'menu_name'          => 'Courses',
		),
		'public'             => true,
		'hierarchical'       => true,
		'has_archive'        => true,
		'rewrite'            => array( 'slug' => 'courses', 'with_front' => false ),
		'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes', 'custom-fields' ),
		'show_in_rest'       => true,
		'menu_icon'          => 'dashicons-welcome-learn-more',
		'menu_position'      => 7,
	) );
}
add_action( 'init', 'sparklab_courses_register_cpt' );

/* ──────────────────────────────────────────────────
   Meta Fields
   ────────────────────────────────────────────────── */

function sparklab_courses_register_meta() {
	$fields = array(
		'_sparklab_course_duration'      => 'string',
		'_sparklab_course_icon'          => 'string',
		'_sparklab_course_quiz_question' => 'string',
		'_sparklab_course_quiz_options'  => 'string',   // JSON array
		'_sparklab_course_quiz_correct'  => 'integer',
	);

	foreach ( $fields as $key => $type ) {
		register_post_meta( 'sparklab_course', $key, array(
			'show_in_rest'      => true,
			'single'            => true,
			'type'              => $type,
			'sanitize_callback' => $type === 'integer' ? 'absint' : 'sanitize_text_field',
			'auth_callback'     => function () { return current_user_can( 'edit_posts' ); },
		) );
	}
}
add_action( 'init', 'sparklab_courses_register_meta' );

/* ──────────────────────────────────────────────────
   Meta Boxes
   ────────────────────────────────────────────────── */

function sparklab_courses_add_meta_boxes() {
	add_meta_box( 'sparklab_course_settings', 'Course Settings', 'sparklab_courses_settings_box', 'sparklab_course', 'side', 'high' );
	add_meta_box( 'sparklab_course_quiz', 'Course Quiz', 'sparklab_courses_quiz_box', 'sparklab_course', 'normal', 'default' );
}
add_action( 'add_meta_boxes', 'sparklab_courses_add_meta_boxes' );

function sparklab_courses_settings_box( $post ) {
	wp_nonce_field( 'sparklab_course_save', 'sparklab_course_nonce' );

	$duration = get_post_meta( $post->ID, '_sparklab_course_duration', true );
	$icon     = get_post_meta( $post->ID, '_sparklab_course_icon', true ) ?: 'shield';

	$icon_options = array(
		'shield' => 'Shield (Safety)',
		'cube'   => 'Cube (Equipment)',
		'layers' => 'Layers (Software)',
		'tool'   => 'Tool (Maintenance)',
	);
	?>
	<p>
		<label for="sparklab_course_duration"><strong>Duration</strong></label><br>
		<input type="text" id="sparklab_course_duration" name="_sparklab_course_duration"
		       value="<?php echo esc_attr( $duration ); ?>" placeholder="e.g. 15 min" class="widefat">
	</p>
	<p>
		<label for="sparklab_course_icon"><strong>Icon</strong></label><br>
		<select id="sparklab_course_icon" name="_sparklab_course_icon" class="widefat">
			<?php foreach ( $icon_options as $val => $label ) : ?>
				<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $icon, $val ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<p class="description">These settings apply to top-level courses. Child modules inherit their parent's settings.</p>
	<?php
}

function sparklab_courses_quiz_box( $post ) {
	$question     = get_post_meta( $post->ID, '_sparklab_course_quiz_question', true );
	$options_json = get_post_meta( $post->ID, '_sparklab_course_quiz_options', true ) ?: '[]';
	$options      = json_decode( $options_json, true );
	if ( ! is_array( $options ) ) $options = array();
	while ( count( $options ) < 4 ) $options[] = '';
	$correct = (int) get_post_meta( $post->ID, '_sparklab_course_quiz_correct', true );
	?>
	<p class="description">Add a certification quiz to this course. Students must answer correctly to complete. Only applies to parent courses.</p>
	<table class="form-table">
		<tr>
			<th><label for="sparklab_quiz_q">Question</label></th>
			<td><input type="text" id="sparklab_quiz_q" name="_sparklab_course_quiz_question"
			           value="<?php echo esc_attr( $question ); ?>" class="widefat" placeholder="Enter quiz question"></td>
		</tr>
		<?php for ( $i = 0; $i < 4; $i++ ) : ?>
		<tr>
			<th><label>Option <?php echo $i + 1; ?></label></th>
			<td><input type="text" name="_sparklab_course_quiz_options[]"
			           value="<?php echo esc_attr( $options[ $i ] ?? '' ); ?>" class="widefat"
			           placeholder="Answer option <?php echo $i + 1; ?>"></td>
		</tr>
		<?php endfor; ?>
		<tr>
			<th><label for="sparklab_quiz_correct">Correct Answer</label></th>
			<td>
				<select id="sparklab_quiz_correct" name="_sparklab_course_quiz_correct">
					<?php for ( $i = 0; $i < 4; $i++ ) : ?>
						<option value="<?php echo $i; ?>" <?php selected( $correct, $i ); ?>>Option <?php echo $i + 1; ?></option>
					<?php endfor; ?>
				</select>
			</td>
		</tr>
	</table>
	<?php
}

function sparklab_courses_save_meta( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! isset( $_POST['sparklab_course_nonce'] ) ) return;
	if ( ! wp_verify_nonce( $_POST['sparklab_course_nonce'], 'sparklab_course_save' ) ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	// Settings.
	$text_fields = array( '_sparklab_course_duration', '_sparklab_course_icon', '_sparklab_course_quiz_question' );
	foreach ( $text_fields as $key ) {
		if ( isset( $_POST[ $key ] ) ) {
			update_post_meta( $post_id, $key, sanitize_text_field( $_POST[ $key ] ) );
		}
	}

	// Quiz options (array → JSON).
	if ( isset( $_POST['_sparklab_course_quiz_options'] ) && is_array( $_POST['_sparklab_course_quiz_options'] ) ) {
		$opts = array_map( 'sanitize_text_field', $_POST['_sparklab_course_quiz_options'] );
		update_post_meta( $post_id, '_sparklab_course_quiz_options', wp_json_encode( $opts ) );
	}

	// Correct answer index.
	if ( isset( $_POST['_sparklab_course_quiz_correct'] ) ) {
		update_post_meta( $post_id, '_sparklab_course_quiz_correct', absint( $_POST['_sparklab_course_quiz_correct'] ) );
	}
}
add_action( 'save_post_sparklab_course', 'sparklab_courses_save_meta' );

/* ──────────────────────────────────────────────────
   Enqueue front-end assets
   ────────────────────────────────────────────────── */

function sparklab_courses_enqueue() {
	if ( ! is_post_type_archive( 'sparklab_course' ) && ! is_singular( 'sparklab_course' ) ) {
		return;
	}

	wp_enqueue_style(
		'sparklab-courses',
		SPARKLAB_COURSES_URL . 'assets/css/courses.css',
		array(),
		filemtime( SPARKLAB_COURSES_PATH . 'assets/css/courses.css' )
	);

	wp_enqueue_script(
		'sparklab-courses',
		SPARKLAB_COURSES_URL . 'assets/js/courses.js',
		array(),
		filemtime( SPARKLAB_COURSES_PATH . 'assets/js/courses.js' ),
		true
	);

	// Pass quiz data for single course pages.
	if ( is_singular( 'sparklab_course' ) ) {
		global $post;
		$course_post = $post->post_parent ? get_post( $post->post_parent ) : $post;
		$question    = get_post_meta( $course_post->ID, '_sparklab_course_quiz_question', true );

		if ( $question ) {
			$options_json = get_post_meta( $course_post->ID, '_sparklab_course_quiz_options', true ) ?: '[]';
			wp_localize_script( 'sparklab-courses', 'slcQuiz', array(
				'courseSlug' => $course_post->post_name,
				'question'   => $question,
				'options'    => json_decode( $options_json, true ),
				'correct'    => (int) get_post_meta( $course_post->ID, '_sparklab_course_quiz_correct', true ),
			) );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'sparklab_courses_enqueue' );

/* ──────────────────────────────────────────────────
   Content Import — one-click demo content
   ────────────────────────────────────────────────── */

function sparklab_courses_admin_notice() {
	$existing = get_posts( array( 'post_type' => 'sparklab_course', 'posts_per_page' => 1, 'post_status' => 'any' ) );
	if ( ! empty( $existing ) ) return;

	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, array( 'edit-sparklab_course', 'sparklab_course', 'plugins', 'dashboard' ), true ) ) return;
	?>
	<div class="notice notice-info is-dismissible">
		<p>
			<strong>SparkLab Courses:</strong> Import demo content (4 courses + 10 modules with Gutenberg blocks)?
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sparklab_import_courses' ), 'sparklab_import_courses' ) ); ?>"
			   class="button button-primary" style="margin-left:12px;">Import Course Content</a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'sparklab_courses_admin_notice' );

function sparklab_courses_handle_import() {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
	check_admin_referer( 'sparklab_import_courses' );

	sparklab_courses_import_content();

	wp_safe_redirect( admin_url( 'edit.php?post_type=sparklab_course&imported=1' ) );
	exit;
}
add_action( 'admin_post_sparklab_import_courses', 'sparklab_courses_handle_import' );

function sparklab_courses_import_notice() {
	if ( isset( $_GET['imported'] ) && '1' === $_GET['imported'] ) {
		echo '<div class="notice notice-success is-dismissible"><p><strong>SparkLab Courses:</strong> Demo content imported successfully!</p></div>';
	}
}
add_action( 'admin_notices', 'sparklab_courses_import_notice' );

/**
 * Convert plain text (with **bold** and - bullets) to Gutenberg block markup.
 */
function sparklab_courses_text_to_blocks( $text ) {
	$lines      = explode( "\n", trim( $text ) );
	$blocks     = '';
	$list_items = array();

	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( '' === $line ) continue;

		$line = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $line );

		if ( 0 === strpos( $line, '- ' ) ) {
			$list_items[] = '<li>' . substr( $line, 2 ) . '</li>';
		} else {
			if ( ! empty( $list_items ) ) {
				$blocks .= "<!-- wp:list -->\n<ul class=\"wp-block-list\">" . implode( '', $list_items ) . "</ul>\n<!-- /wp:list -->\n\n";
				$list_items = array();
			}
			$blocks .= "<!-- wp:paragraph -->\n<p>" . $line . "</p>\n<!-- /wp:paragraph -->\n\n";
		}
	}

	if ( ! empty( $list_items ) ) {
		$blocks .= "<!-- wp:list -->\n<ul class=\"wp-block-list\">" . implode( '', $list_items ) . "</ul>\n<!-- /wp:list -->\n\n";
	}

	return trim( $blocks );
}

/**
 * Create all course + module posts from the original React app data.
 */
function sparklab_courses_import_content() {
	$courses = array(
		array(
			'title'    => 'Lab Safety & A1 Mini Overview',
			'excerpt'  => 'Essential safety protocols and introduction to the Bambu Lab A1 Mini printer.',
			'duration' => '15 min',
			'icon'     => 'shield',
			'order'    => 1,
			'quiz'     => array(
				'question' => 'What is the maximum nozzle temperature of the Bambu Lab A1 Mini?',
				'options'  => array( '200°C', '250°C', '300°C', '350°C' ),
				'correct'  => 2,
			),
			'modules'  => array(
				array(
					'title' => 'Safety Fundamentals',
					'order' => 1,
					'text'  => "Before using the Bambu Lab A1 Mini, understand these safety essentials.\n- **Hot Surfaces:** The nozzle reaches 300°C and the bed reaches 80°C. Never touch during or immediately after printing.\n- **Moving Parts:** Keep hands, hair, and loose clothing away from the moving print head and bed.\n- **Ventilation:** Print PLA in well-ventilated areas. For PETG or TPU, ensure proper airflow.\n- **Power Safety:** Always use the included power adapter. Never unplug during a print.",
				),
				array(
					'title' => 'A1 Mini Overview',
					'order' => 2,
					'text'  => "The Bambu Lab A1 Mini is a compact, high-speed FDM printer perfect for beginners and pros.\n- **Build Volume:** 180 × 180 × 180 mm — ideal for small to medium prints.\n- **Print Speed:** Up to 500 mm/s with 20,000 mm/s² acceleration.\n- **Auto Bed Leveling:** Built-in Lidar and force sensor for perfect first layers every time.\n- **Connectivity:** WiFi-enabled with Bambu Studio and Bambu Handy app support.",
				),
			),
		),
		array(
			'title'    => 'Operating the A1 Mini',
			'excerpt'  => 'Learn to set up, load filament, and start your first print on the Bambu A1 Mini.',
			'duration' => '25 min',
			'icon'     => 'cube',
			'order'    => 2,
			'quiz'     => array(
				'question' => 'What angle should you cut the filament tip before loading?',
				'options'  => array( '90° (flat)', '45° angle', 'No cutting needed', 'Round the tip' ),
				'correct'  => 1,
			),
			'modules'  => array(
				array(
					'title' => 'Machine Components',
					'order' => 1,
					'text'  => "Understanding your A1 Mini's key components.\n- **Hotend:** All-metal design supporting temps up to 300°C for PLA, PETG, TPU, and PLA-CF.\n- **Textured PEI Build Plate:** Flexible magnetic plate with excellent adhesion. No glue needed for PLA!\n- **Filament Spool Holder:** Rear-mounted holder. Ensure filament feeds smoothly without tangles.\n- **Control Screen:** Touchscreen for manual controls, calibration, and print monitoring.",
				),
				array(
					'title' => 'Loading Filament',
					'order' => 2,
					'text'  => "Follow these steps to load filament correctly.\n- **Step 1:** Place your filament spool on the holder with the filament unwinding from the bottom.\n- **Step 2:** Cut the filament tip at a 45° angle for smooth feeding.\n- **Step 3:** On the touchscreen, go to Settings → Filament → Load and follow the prompts.\n- **Step 4:** Wait for filament to extrude from the nozzle, confirming successful loading.",
				),
				array(
					'title' => 'Starting Your First Print',
					'order' => 3,
					'text'  => "Ready to print? Here's the workflow.\n- **Send from Bambu Studio:** Slice your model and click \"Print\" to send wirelessly to the A1 Mini.\n- **Auto-Calibration:** The printer runs vibration compensation and bed leveling automatically.\n- **First Layer Check:** Watch the first layer. It should be smooth and well-adhered to the bed.\n- **Monitor Progress:** Use the touchscreen or Bambu Handy app to track print progress.",
				),
			),
		),
		array(
			'title'    => 'Bambu Studio Slicer',
			'excerpt'  => 'Master Bambu Studio to prepare and optimize your 3D models for printing.',
			'duration' => '20 min',
			'icon'     => 'layers',
			'order'    => 3,
			'quiz'     => array(
				'question' => 'What infill percentage is recommended for functional parts that need strength?',
				'options'  => array( '5%', '15%', '30% or higher', '100%' ),
				'correct'  => 2,
			),
			'modules'  => array(
				array(
					'title' => 'Bambu Studio Basics',
					'order' => 1,
					'text'  => "Bambu Studio is the official slicer for Bambu Lab printers.\n- **Download:** Get it free from bambulab.com. Available for Windows, macOS, and Linux.\n- **Import Models:** Drag and drop STL, OBJ, STEP, or 3MF files onto the build plate.\n- **Printer Selection:** Select \"Bambu Lab A1 Mini 0.4mm nozzle\" from the printer dropdown.\n- **Filament Profile:** Choose your filament type (PLA, PETG, etc.) for optimal settings.",
				),
				array(
					'title' => 'Key Print Settings',
					'order' => 2,
					'text'  => "Understanding the most important slicer settings.\n- **Layer Height:** 0.2mm (standard) or 0.12mm (high detail). Affects print time and quality.\n- **Infill:** 15% for decorative, 30% for functional, 50%+ for strong parts.\n- **Supports:** Enable for overhangs over 45°. Use \"tree supports\" for easier removal.\n- **Brim/Raft:** Add a brim for better bed adhesion on small or tall prints.",
				),
				array(
					'title' => 'Sending to Printer',
					'order' => 3,
					'text'  => "Multiple ways to start your print.\n- **WiFi Direct:** Click \"Print\" in Bambu Studio to send directly to your networked A1 Mini.\n- **SD Card:** Export the .3mf file to a microSD card and insert into the printer.\n- **Bambu Handy:** Send prints and monitor remotely from your smartphone.\n- **Print Preview:** Always review the sliced preview to check for issues before printing.",
				),
			),
		),
		array(
			'title'    => 'Troubleshooting & Tips',
			'excerpt'  => 'Common issues, solutions, and pro tips for successful A1 Mini printing.',
			'duration' => '15 min',
			'icon'     => 'tool',
			'order'    => 4,
			'quiz'     => array(
				'question' => 'What should you use to clean the PEI build plate for better adhesion?',
				'options'  => array( 'Water only', 'Isopropyl Alcohol (IPA)', 'Acetone', 'No cleaning needed' ),
				'correct'  => 1,
			),
			'modules'  => array(
				array(
					'title' => 'Common Print Issues',
					'order' => 1,
					'text'  => "How to identify and fix typical printing problems.\n- **First Layer Not Sticking:** Clean the PEI plate with IPA. Ensure proper Z-offset calibration.\n- **Stringing:** Increase retraction or lower print temperature by 5–10°C.\n- **Layer Shifting:** Check belt tension and ensure the printer is on a stable surface.\n- **Under-Extrusion:** Check for clogs, ensure correct filament diameter in slicer (1.75mm).",
				),
				array(
					'title' => 'Maintenance Best Practices',
					'order' => 2,
					'text'  => "Keep your A1 Mini running smoothly.\n- **Clean the Bed:** Wipe with IPA before each print. Deep clean with dish soap weekly.\n- **Check the Nozzle:** Inspect for wear every 500 print hours. Replace if needed.\n- **Lubricate Rails:** Apply light machine oil to linear rails every few months.\n- **Firmware Updates:** Keep firmware updated via Bambu Studio for best performance.",
				),
			),
		),
	);

	foreach ( $courses as $data ) {
		// Create parent course.
		$parent_id = wp_insert_post( array(
			'post_type'    => 'sparklab_course',
			'post_title'   => $data['title'],
			'post_excerpt' => $data['excerpt'],
			'post_status'  => 'publish',
			'menu_order'   => $data['order'],
			'post_content' => '',
		) );

		if ( is_wp_error( $parent_id ) ) continue;

		update_post_meta( $parent_id, '_sparklab_course_duration', $data['duration'] );
		update_post_meta( $parent_id, '_sparklab_course_icon', $data['icon'] );
		update_post_meta( $parent_id, '_sparklab_course_quiz_question', $data['quiz']['question'] );
		update_post_meta( $parent_id, '_sparklab_course_quiz_options', wp_json_encode( $data['quiz']['options'] ) );
		update_post_meta( $parent_id, '_sparklab_course_quiz_correct', $data['quiz']['correct'] );

		// Create child modules.
		foreach ( $data['modules'] as $mod ) {
			wp_insert_post( array(
				'post_type'    => 'sparklab_course',
				'post_title'   => $mod['title'],
				'post_parent'  => $parent_id,
				'post_status'  => 'publish',
				'menu_order'   => $mod['order'],
				'post_content' => sparklab_courses_text_to_blocks( $mod['text'] ),
			) );
		}
	}

	// Flush rewrite rules after import.
	flush_rewrite_rules();
}

/* ──────────────────────────────────────────────────
   Activation / Deactivation
   ────────────────────────────────────────────────── */

function sparklab_courses_activate() {
	sparklab_courses_register_cpt();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'sparklab_courses_activate' );

function sparklab_courses_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'sparklab_courses_deactivate' );
