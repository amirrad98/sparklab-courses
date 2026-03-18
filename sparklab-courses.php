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
   Content Import — HTML source import
   ────────────────────────────────────────────────── */

function sparklab_courses_default_import_path() {
	return '/Users/amiretminanrad/Downloads/bambu-academy-a1-content.html';
}

function sparklab_courses_admin_notice() {
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, array( 'edit-sparklab_course', 'sparklab_course', 'plugins', 'dashboard' ), true ) ) {
		return;
	}
	?>
	<div class="notice notice-info is-dismissible">
		<p>
			<strong>SparkLab Courses:</strong> Import the Bambu Lab A1 Academy HTML source. This replaces existing course posts.
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sparklab_import_courses' ), 'sparklab_import_courses' ) ); ?>"
			   class="button button-primary" style="margin-left:12px;">Import Course Content</a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'sparklab_courses_admin_notice' );

function sparklab_courses_handle_import() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}
	check_admin_referer( 'sparklab_import_courses' );

	$result = sparklab_courses_import_content();
	if ( is_wp_error( $result ) ) {
		wp_die( esc_html( $result->get_error_message() ) );
	}

	wp_safe_redirect( admin_url( 'edit.php?post_type=sparklab_course&imported=1' ) );
	exit;
}
add_action( 'admin_post_sparklab_import_courses', 'sparklab_courses_handle_import' );

function sparklab_courses_import_notice() {
	if ( isset( $_GET['imported'] ) && '1' === $_GET['imported'] ) {
		echo '<div class="notice notice-success is-dismissible"><p><strong>SparkLab Courses:</strong> Academy content imported successfully.</p></div>';
	}
}
add_action( 'admin_notices', 'sparklab_courses_import_notice' );

function sparklab_courses_wrap_block( $name, $inner_html, $attrs = array() ) {
	$comment = '<!-- wp:' . $name;
	if ( ! empty( $attrs ) ) {
		$comment .= ' ' . wp_json_encode( $attrs );
	}
	$comment .= " -->\n" . trim( $inner_html ) . "\n<!-- /wp:" . $name . " -->\n\n";
	return $comment;
}

function sparklab_courses_dom_inner_html( DOMNode $node ) {
	$html = '';

	foreach ( $node->childNodes as $child ) {
		$html .= $node->ownerDocument->saveHTML( $child );
	}

	return $html;
}

function sparklab_courses_dom_has_block_children( DOMNode $node ) {
	$block_tags = array( 'blockquote', 'div', 'figure', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ol', 'p', 'table', 'ul' );

	foreach ( $node->childNodes as $child ) {
		if ( XML_ELEMENT_NODE !== $child->nodeType ) {
			continue;
		}

		if ( in_array( strtolower( $child->nodeName ), $block_tags, true ) ) {
			return true;
		}
	}

	return false;
}

function sparklab_courses_upload_import_image( $data_url ) {
	static $upload_cache = null;

	$data_url = html_entity_decode( trim( (string) $data_url ), ENT_QUOTES, 'UTF-8' );
	if ( '' === $data_url ) {
		return '';
	}

	if ( ! preg_match( '#^data:image/([a-zA-Z0-9.+-]+);base64,(.+)$#s', $data_url, $matches ) ) {
		return '';
	}

	$extension = strtolower( $matches[1] );
	$extension = str_replace( '+xml', '', $extension );
	if ( 'jpeg' === $extension ) {
		$extension = 'jpg';
	}

	$binary = base64_decode( preg_replace( '/\s+/', '', $matches[2] ), true );
	if ( false === $binary || '' === $binary ) {
		return '';
	}

	if ( null === $upload_cache ) {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return '';
		}

		$directory = trailingslashit( $uploads['basedir'] ) . 'courses';
		if ( ! wp_mkdir_p( $directory ) ) {
			return '';
		}

		$upload_cache = array(
			'dir' => $directory,
			'url' => trailingslashit( $uploads['baseurl'] ) . 'courses',
		);
	}

	$hash     = sha1( $binary );
	$filename = sanitize_file_name( 'academy-inline-' . $hash . '.' . $extension );
	$filepath = trailingslashit( $upload_cache['dir'] ) . $filename;

	if ( ! file_exists( $filepath ) ) {
		file_put_contents( $filepath, $binary );
	}

	return trailingslashit( $upload_cache['url'] ) . $filename;
}

function sparklab_courses_normalize_image_tag( $tag ) {
	if ( ! preg_match( '/\bsrc\s*=\s*(["\'])(.*?)\1/is', $tag, $matches ) ) {
		return $tag;
	}

	$src = html_entity_decode( trim( $matches[2] ), ENT_QUOTES, 'UTF-8' );
	$alt = '';
	if ( preg_match( '/\balt\s*=\s*(["\'])(.*?)\1/is', $tag, $alt_matches ) ) {
		$alt = html_entity_decode( trim( $alt_matches[2] ), ENT_QUOTES, 'UTF-8' );
	}

	if ( 0 === strpos( $src, 'data:image' ) ) {
		$src = sparklab_courses_upload_import_image( $src );
	}

	if ( '' === $src ) {
		return '';
	}

	return '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async" />';
}

function sparklab_courses_is_image_count_paragraph( DOMNode $node, $text ) {
	$text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
	if ( ! preg_match( '/^\d+\s+images?$/i', $text ) ) {
		return false;
	}

	if ( $node instanceof DOMElement ) {
		$class_name = strtolower( trim( (string) $node->getAttribute( 'class' ) ) );
		if ( '' !== $class_name ) {
			$classes = preg_split( '/\s+/', $class_name );
			if ( in_array( 'im', $classes, true ) ) {
				return true;
			}
		}
	}

	return false;
}

function sparklab_courses_image_block_from_node( DOMNode $node ) {
	if ( 'img' !== strtolower( $node->nodeName ) && 'figure' !== strtolower( $node->nodeName ) ) {
		return '';
	}

	$img_node = $node;
	if ( 'figure' === strtolower( $node->nodeName ) ) {
		$img_node = null;
		foreach ( $node->childNodes as $child ) {
			if ( XML_ELEMENT_NODE === $child->nodeType && 'img' === strtolower( $child->nodeName ) ) {
				$img_node = $child;
				break;
			}
			if ( XML_ELEMENT_NODE === $child->nodeType && 'a' === strtolower( $child->nodeName ) ) {
				foreach ( $child->childNodes as $grandchild ) {
					if ( XML_ELEMENT_NODE === $grandchild->nodeType && 'img' === strtolower( $grandchild->nodeName ) ) {
						$img_node = $grandchild;
						break 2;
					}
				}
			}
		}
		if ( ! $img_node ) {
			return '';
		}
	}

	$src = trim( (string) $img_node->getAttribute( 'src' ) );
	if ( 0 === strpos( $src, 'data:image' ) ) {
		$src = sparklab_courses_upload_import_image( $src );
	}

	if ( '' === $src ) {
		return '';
	}

	$alt = trim( (string) $img_node->getAttribute( 'alt' ) );
	$caption = '';
	if ( 'figure' === strtolower( $node->nodeName ) ) {
		foreach ( $node->childNodes as $child ) {
			if ( XML_ELEMENT_NODE === $child->nodeType && 'figcaption' === strtolower( $child->nodeName ) ) {
				$caption = trim( sparklab_courses_dom_inner_html( $child ) );
				break;
			}
		}
	}

	$figure = '<figure class="wp-block-image size-full"><img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '" />';
	if ( '' !== $caption ) {
		$figure .= '<figcaption>' . wp_kses_post( $caption ) . '</figcaption>';
	}
	$figure .= '</figure>';

	return sparklab_courses_wrap_block(
		'image',
		$figure,
		array(
			'sizeSlug'        => 'full',
			'linkDestination' => 'none',
		)
	);
}

function sparklab_courses_extract_youtube_id_from_url( $url ) {
	$url = html_entity_decode( trim( (string) $url ), ENT_QUOTES, 'UTF-8' );
	if ( '' === $url ) {
		return '';
	}

	$parts = wp_parse_url( $url );
	$host  = strtolower( $parts['host'] ?? '' );
	$path  = $parts['path'] ?? '';

	if ( false !== strpos( $host, 'youtu.be' ) ) {
		return trim( $path, '/' );
	}

	if ( false !== strpos( $host, 'youtube.com' ) || false !== strpos( $host, 'youtube-nocookie.com' ) ) {
		if ( preg_match( '#/embed/([^/?&"\']+)#', $path, $matches ) ) {
			return $matches[1];
		}

		parse_str( $parts['query'] ?? '', $query_args );
		if ( ! empty( $query_args['v'] ) ) {
			return $query_args['v'];
		}
	}

	return '';
}

function sparklab_courses_youtube_block_from_src( $src, $title = '' ) {
	$video_id = sparklab_courses_extract_youtube_id_from_url( $src );
	if ( '' === $video_id ) {
		return '';
	}

	$label = trim( (string) $title );
	if ( '' === $label ) {
		$label = 'Course video';
	}

	$thumb = 'https://i.ytimg.com/vi/' . rawurlencode( $video_id ) . '/hqdefault.jpg';
	$html  = '<div class="slc-lite-video" data-youtube-src="' . esc_url( $src ) . '" data-youtube-title="' . esc_attr( $label ) . '">';
	$html .= '<button type="button" class="slc-lite-video__button" aria-label="' . esc_attr( 'Play video: ' . $label ) . '">';
	$html .= '<span class="slc-lite-video__thumb" style="background-image:url(' . esc_url( $thumb ) . ');"></span>';
	$html .= '<span class="slc-lite-video__overlay"></span>';
	$html .= '<span class="slc-lite-video__play">';
	$html .= '<span class="slc-lite-video__play-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><polygon points="8 5 19 12 8 19 8 5"></polygon></svg></span>';
	$html .= '<span class="slc-lite-video__play-label">Play Video</span>';
	$html .= '</span>';
	$html .= '</button>';
	$html .= '</div>';

	return sparklab_courses_wrap_block( 'html', $html );
}

function sparklab_courses_dom_node_to_blocks( DOMDocument $dom, DOMNode $node ) {
	if ( XML_TEXT_NODE === $node->nodeType ) {
		$text = trim( preg_replace( '/\s+/u', ' ', $node->textContent ) );
		return '' === $text ? '' : esc_html( $text );
	}

	if ( XML_ELEMENT_NODE !== $node->nodeType ) {
		return '';
	}

	$tag = strtolower( $node->nodeName );

	switch ( $tag ) {
		case 'script':
		case 'style':
			return '';

		case 'h1':
		case 'h2':
		case 'h3':
		case 'h4':
		case 'h5':
		case 'h6':
			$level = (int) substr( $tag, 1 );
			$inner  = trim( sparklab_courses_dom_inner_html( $node ) );
			if ( '' === trim( wp_strip_all_tags( $inner ) ) ) {
				return '';
			}
			return sparklab_courses_wrap_block(
				'heading',
				'<' . $tag . '>' . $inner . '</' . $tag . '>',
				array( 'level' => $level )
			);

		case 'p':
			$inner = trim( sparklab_courses_dom_inner_html( $node ) );
			if ( '' === trim( wp_strip_all_tags( $inner ) ) ) {
				return '';
			}
			if ( sparklab_courses_is_image_count_paragraph( $node, wp_strip_all_tags( $inner ) ) ) {
				return '';
			}
			return sparklab_courses_wrap_block( 'paragraph', '<p>' . $inner . '</p>' );

		case 'blockquote':
			$inner = trim( sparklab_courses_dom_inner_html( $node ) );
			if ( '' === trim( wp_strip_all_tags( $inner ) ) ) {
				return '';
			}
			return sparklab_courses_wrap_block( 'quote', '<blockquote class="wp-block-quote">' . $inner . '</blockquote>' );

		case 'ul':
		case 'ol':
			$items = '';
			foreach ( $node->childNodes as $child ) {
				if ( XML_ELEMENT_NODE !== $child->nodeType || 'li' !== strtolower( $child->nodeName ) ) {
					continue;
				}
				$li_inner = trim( sparklab_courses_dom_inner_html( $child ) );
				if ( '' === trim( wp_strip_all_tags( $li_inner ) ) ) {
					continue;
				}
				$items .= '<li>' . $li_inner . '</li>';
			}
			if ( '' === $items ) {
				return '';
			}
			return sparklab_courses_wrap_block( 'list', '<' . $tag . ' class="wp-block-list">' . $items . '</' . $tag . '>' );

		case 'figure':
			$image_block = sparklab_courses_image_block_from_node( $node );
			if ( '' !== $image_block ) {
				return $image_block;
			}
			$inner = trim( sparklab_courses_dom_inner_html( $node ) );
			return '' === trim( wp_strip_all_tags( $inner ) ) ? '' : sparklab_courses_wrap_block( 'html', $inner );

		case 'img':
			return sparklab_courses_image_block_from_node( $node );

		case 'table':
			return sparklab_courses_wrap_block( 'html', trim( $dom->saveHTML( $node ) ) );

		case 'iframe':
			$youtube_block = sparklab_courses_youtube_block_from_src(
				(string) $node->getAttribute( 'src' ),
				(string) $node->getAttribute( 'title' )
			);
			if ( '' !== $youtube_block ) {
				return $youtube_block;
			}
			return sparklab_courses_wrap_block( 'html', trim( $dom->saveHTML( $node ) ) );

		case 'div':
			$class = (string) $node->getAttribute( 'class' );
			if ( false !== strpos( $class, 'raw-html-embed' ) && $node->getElementsByTagName( 'iframe' )->length > 0 ) {
				$iframe = $node->getElementsByTagName( 'iframe' )->item( 0 );
				if ( $iframe instanceof DOMElement ) {
					$youtube_block = sparklab_courses_youtube_block_from_src(
						(string) $iframe->getAttribute( 'src' ),
						(string) $iframe->getAttribute( 'title' )
					);
					if ( '' !== $youtube_block ) {
						return $youtube_block;
					}
				}
			}
			if ( false !== strpos( $class, 'raw-html-embed' ) ) {
				return sparklab_courses_wrap_block( 'html', trim( $dom->saveHTML( $node ) ) );
			}
			if ( sparklab_courses_dom_has_block_children( $node ) || $node->getElementsByTagName( 'iframe' )->length > 0 ) {
				$children = '';
				foreach ( $node->childNodes as $child ) {
					$children .= sparklab_courses_dom_node_to_blocks( $dom, $child );
				}
				return $children;
			}
			$inner = trim( sparklab_courses_dom_inner_html( $node ) );
			if ( '' === trim( wp_strip_all_tags( $inner ) ) ) {
				return '';
			}
			return sparklab_courses_wrap_block( 'paragraph', '<p>' . $inner . '</p>' );

		default:
			$inner = trim( sparklab_courses_dom_inner_html( $node ) );
			if ( '' === trim( wp_strip_all_tags( $inner ) ) ) {
				return '';
			}
			if ( sparklab_courses_dom_has_block_children( $node ) ) {
				$children = '';
				foreach ( $node->childNodes as $child ) {
					$children .= sparklab_courses_dom_node_to_blocks( $dom, $child );
				}
				return $children;
			}
			return sparklab_courses_wrap_block( 'html', trim( $dom->saveHTML( $node ) ) );
	}
}

function sparklab_courses_html_fragment_to_blocks( $html ) {
	$html = preg_replace( '#<figure\b[^>]*>\s*(?:<a\b[^>]*>\s*</a>)?\s*</figure>#is', '', $html );
	$html = preg_replace( '#<p>\s*(?:&nbsp;|\x{00A0})\s*</p>#iu', '', $html );

	libxml_use_internal_errors( true );
	$dom = new DOMDocument( '1.0', 'UTF-8' );
	$loaded = $dom->loadHTML(
		'<?xml encoding="utf-8" ?><div id="sparklab-fragment">' . $html . '</div>',
		LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET | LIBXML_COMPACT
	);
	libxml_clear_errors();

	if ( ! $loaded ) {
		return wp_kses_post( $html );
	}

	$xpath = new DOMXPath( $dom );
	$root   = $xpath->query( '//*[@id="sparklab-fragment"]' )->item( 0 );
	if ( ! $root ) {
		return wp_kses_post( $html );
	}

	foreach ( $xpath->query( './/figure[not(.//img) and not(normalize-space())]', $root ) as $empty_figure ) {
		$empty_figure->parentNode->removeChild( $empty_figure );
	}

	$blocks = '';
	foreach ( $root->childNodes as $child ) {
		$blocks .= sparklab_courses_dom_node_to_blocks( $dom, $child );
	}

	return trim( $blocks );
}

function sparklab_courses_level_definition( $level_key ) {
	$levels = array(
		'beginner' => array(
			'title'    => 'Beginner',
			'order'    => 1,
			'icon'     => 'shield',
			'duration' => '~60 min',
			'excerpt'  => '13 chapters covering unboxing, placement, setup, filament loading, and first prints.',
		),
		'intermediate' => array(
			'title'    => 'Intermediate',
			'order'    => 2,
			'icon'     => 'layers',
			'duration' => '~45 min',
			'excerpt'  => '9 chapters covering materials, slicer settings, SD card printing, and maintenance.',
		),
		'advanced' => array(
			'title'    => 'Advanced',
			'order'    => 3,
			'icon'     => 'tool',
			'duration' => '~50 min',
			'excerpt'  => '9 chapters covering troubleshooting, mechanical service, AMS workflow, and calibration.',
		),
	);

	return $levels[ strtolower( $level_key ) ] ?? array(
		'title'    => ucfirst( strtolower( $level_key ) ),
		'order'    => 99,
		'icon'     => 'shield',
		'duration' => '~45 min',
		'excerpt'  => 'Imported course content.',
	);
}

function sparklab_courses_strip_data_image_tags_from_chunk( $chunk, &$skip_img_tag, &$carry ) {
	if ( '' !== $carry ) {
		$chunk = $carry . $chunk;
		$carry = '';
	}

	$skip_img_tag = false;
	$result = '';
	$offset = 0;
	$length = strlen( $chunk );

	while ( $offset < $length ) {
		$img_pos = stripos( $chunk, '<img', $offset );
		if ( false === $img_pos ) {
			$result .= substr( $chunk, $offset );
			break;
		}

		$result .= substr( $chunk, $offset, $img_pos - $offset );

		$tag_end = strpos( $chunk, '>', $img_pos );
		if ( false === $tag_end ) {
			$carry = substr( $chunk, $img_pos );
			break;
		}

		$tag = substr( $chunk, $img_pos, $tag_end - $img_pos + 1 );
		$result .= sparklab_courses_normalize_image_tag( $tag );
		$offset = $tag_end + 1;
	}

	return $result;
}

function sparklab_courses_parse_academy_html( $source_path ) {
	if ( ! file_exists( $source_path ) ) {
		return new WP_Error( 'sparklab_courses_missing_source', 'Import source file not found: ' . $source_path );
	}

	$handle = fopen( $source_path, 'rb' );
	if ( ! $handle ) {
		return new WP_Error( 'sparklab_courses_open_failed', 'Could not open import source file.' );
	}

	$courses     = array();
	$current     = null;
	$current_mod = null;
	$buffer      = '';
	$skip_img_tag = false;
	$carry        = '';

	try {
		while ( ! feof( $handle ) ) {
			$chunk = fgets( $handle, 8192 );
			if ( false === $chunk ) {
				break;
			}

			$chunk = sparklab_courses_strip_data_image_tags_from_chunk( $chunk, $skip_img_tag, $carry );
			if ( '' === $chunk ) {
				continue;
			}

			$buffer .= $chunk;

			while ( false !== ( $newline = strpos( $buffer, "\n" ) ) ) {
				$line   = trim( substr( $buffer, 0, $newline ) );
				$buffer = substr( $buffer, $newline + 1 );

				if ( '' === $line ) {
					continue;
				}

				if ( preg_match( '/<h2[^>]*class="(beginner|intermediate|advanced)"[^>]*>(.*?)<\/h2>(.*)$/i', $line, $matches ) ) {
					if ( $current_mod && $current ) {
						$current['modules'][] = $current_mod;
						$current_mod = null;
					}
					if ( $current ) {
						$courses[] = $current;
					}

					$level_key  = strtolower( $matches[1] );
					$definition = sparklab_courses_level_definition( $level_key );
					$title_text = trim( wp_strip_all_tags( html_entity_decode( $matches[2], ENT_QUOTES, 'UTF-8' ) ) );
					$chapter_count = 0;
					if ( preg_match( '/\((\d+)\s+chapters?\)/i', $title_text, $count_match ) ) {
						$chapter_count = (int) $count_match[1];
					}

					$current = array(
						'level_key'     => $level_key,
						'title'         => $definition['title'],
						'excerpt'       => $definition['excerpt'],
						'duration'      => $definition['duration'],
						'icon'          => $definition['icon'],
						'order'         => $definition['order'],
						'chapter_count' => $chapter_count,
						'modules'       => array(),
					);
					continue;
				}

				if ( preg_match( '/<h3[^>]*id="([^"]+)"[^>]*>(.*?)<\/h3>(.*)$/i', $line, $matches ) ) {
					if ( ! $current ) {
						continue;
					}

					if ( $current_mod ) {
						$current['modules'][] = $current_mod;
					}

					$module_title = trim( wp_strip_all_tags( html_entity_decode( $matches[2], ENT_QUOTES, 'UTF-8' ) ) );
					$module_title = preg_replace( '/^\d+\.\s*/', '', $module_title );

					$current_mod = array(
						'slug'    => sanitize_title( $current['level_key'] . '-' . $module_title ),
						'title'   => $module_title,
						'order'   => count( $current['modules'] ) + 1,
						'html'    => '',
						'source'  => $matches[1],
					);

					$remainder = trim( $matches[3] );
					if ( '' !== $remainder ) {
						$current_mod['html'] .= $remainder . "\n";
					}
					continue;
				}

				if ( $current_mod ) {
					$current_mod['html'] .= $line . "\n";
				}
			}
		}

		if ( '' !== trim( $buffer ) ) {
			$line = trim( $buffer );
			if ( preg_match( '/<h3[^>]*id="([^"]+)"[^>]*>(.*?)<\/h3>(.*)$/i', $line, $matches ) ) {
				if ( $current_mod && $current ) {
					$current['modules'][] = $current_mod;
					$current_mod = null;
				}
				$module_title = trim( wp_strip_all_tags( html_entity_decode( $matches[2], ENT_QUOTES, 'UTF-8' ) ) );
				$module_title = preg_replace( '/^\d+\.\s*/', '', $module_title );
				$current_mod  = array(
					'slug'   => sanitize_title( ( $current['level_key'] ?? 'lesson' ) . '-' . $module_title ),
					'title'  => $module_title,
					'order'  => count( $current['modules'] ) + 1,
					'html'   => trim( $matches[3] ) . "\n",
					'source' => $matches[1],
				);
			} elseif ( $current_mod ) {
				$current_mod['html'] .= $line . "\n";
			}
		}

		if ( $current_mod && $current ) {
			$current['modules'][] = $current_mod;
		}
		if ( $current ) {
			$courses[] = $current;
		}
	} finally {
		fclose( $handle );
	}

	if ( empty( $courses ) ) {
		return new WP_Error( 'sparklab_courses_parse_failed', 'No course sections were found in the import source.' );
	}

	foreach ( $courses as &$course ) {
		foreach ( $course['modules'] as &$module ) {
			$module['content'] = sparklab_courses_html_fragment_to_blocks( $module['html'] );
			unset( $module['html'] );
		}
		unset( $module );
	}
	unset( $course );

	return $courses;
}

function sparklab_courses_delete_existing_posts() {
	global $wpdb;

	$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'sparklab_course' ORDER BY post_parent DESC, ID DESC" );

	if ( empty( $ids ) ) {
		return;
	}

	usort(
		$ids,
		function ( $a, $b ) {
			$a_parent = (int) get_post_field( 'post_parent', $a );
			$b_parent = (int) get_post_field( 'post_parent', $b );
			if ( $a_parent !== $b_parent ) {
				return $b_parent <=> $a_parent;
			}
			return $b <=> $a;
		}
	);

	foreach ( $ids as $id ) {
		wp_delete_post( (int) $id, true );
	}
}

function sparklab_courses_import_from_html( $source_path = '' ) {
	if ( function_exists( 'ini_set' ) ) {
		@ini_set( 'memory_limit', '1024M' );
	}

	$source_path = apply_filters( 'sparklab_courses_import_source_path', $source_path );
	if ( '' === trim( (string) $source_path ) ) {
		$source_path = sparklab_courses_default_import_path();
	}

	$parsed = sparklab_courses_parse_academy_html( $source_path );
	if ( is_wp_error( $parsed ) ) {
		return $parsed;
	}

	sparklab_courses_delete_existing_posts();

	$kses_disabled = false;
	if ( function_exists( 'kses_remove_filters' ) ) {
		kses_remove_filters();
		$kses_disabled = true;
	}

	try {
		$imported_courses = 0;
		$imported_modules = 0;
		$course_ids       = array();

		foreach ( $parsed as $course_data ) {
			$parent_id = wp_insert_post(
				wp_slash(
					array(
						'post_type'    => 'sparklab_course',
						'post_title'   => $course_data['title'],
						'post_name'    => sanitize_title( $course_data['level_key'] ),
						'post_excerpt' => $course_data['excerpt'],
						'post_status'  => 'publish',
						'menu_order'   => (int) $course_data['order'],
						'post_content' => sparklab_courses_wrap_block(
							'paragraph',
							'<p>Bambu Lab A1 Academy ' . esc_html( $course_data['title'] ) . ' level.</p>'
						),
					)
				)
			);

			if ( is_wp_error( $parent_id ) || ! $parent_id ) {
				return is_wp_error( $parent_id ) ? $parent_id : new WP_Error( 'sparklab_courses_insert_failed', 'Failed to create course post.' );
			}

			$course_ids[] = $parent_id;
			$imported_courses++;

			update_post_meta( $parent_id, '_sparklab_course_duration', $course_data['duration'] );
			update_post_meta( $parent_id, '_sparklab_course_icon', $course_data['icon'] );

			foreach ( $course_data['modules'] as $module_data ) {
				$child_id = wp_insert_post(
					wp_slash(
						array(
							'post_type'    => 'sparklab_course',
							'post_title'   => $module_data['title'],
							'post_name'    => $module_data['slug'],
							'post_parent'  => $parent_id,
							'post_status'  => 'publish',
							'menu_order'   => (int) $module_data['order'],
							'post_content' => $module_data['content'],
						)
					)
				);

				if ( is_wp_error( $child_id ) || ! $child_id ) {
					return is_wp_error( $child_id ) ? $child_id : new WP_Error( 'sparklab_courses_insert_failed', 'Failed to create lesson post.' );
				}

				$imported_modules++;
			}
		}

		flush_rewrite_rules();

		return array(
			'courses' => $imported_courses,
			'modules' => $imported_modules,
			'source'  => $source_path,
			'course_ids' => $course_ids,
		);
	} finally {
		if ( $kses_disabled && function_exists( 'kses_init_filters' ) ) {
			kses_init_filters();
		}
	}
}

function sparklab_courses_import_content() {
	return sparklab_courses_import_from_html();
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
