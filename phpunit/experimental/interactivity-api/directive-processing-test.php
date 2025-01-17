<?php
/**
 * Directive processing test.
 *
 * @package Gutenberg
 * @subpackage Interactivity API
 */

class Tests_Process_Directives extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();

		register_block_type(
			'test/context-level-1',
			array(
				'render_callback' => function ( $attributes, $content ) {
					return '<div data-wp-interactive=\'{ "namespace": "test" }\' data-wp-context=\'{ "myText": "level-1" }\'> <input class="level-1-input-1" data-wp-bind--value="context.myText">' . $content . '<input class="level-1-input-2" data-wp-bind--value="context.myText"></div>';
				},
				'supports'        => array(
					'interactivity' => true,
				),
			)
		);

		register_block_type(
			'test/context-level-2',
			array(
				'render_callback' => function ( $attributes, $content ) {
					return '<div data-wp-interactive=\'{ "namespace": "test" }\' data-wp-context=\'{ "myText": "level-2" }\'><input class="level-2-input-1" data-wp-bind--value="context.myText">' . $content . '</div>';
				},
				'supports'        => array(
					'interactivity' => true,
				),
			)
		);

		register_block_type(
			'test/context-read-only',
			array(
				'render_callback' => function () {
					return '<div data-wp-interactive=\'{ "namespace": "test" }\'><input class="read-only-input-1" data-wp-bind--value="context.myText"></div>';
				},
				'supports'        => array(
					'interactivity' => true,
				),
			)
		);

		register_block_type(
			'test/non-interactive-with-directive',
			array(
				'render_callback' => function () {
					return '<input class="non-interactive-with-directive" data-wp-bind--value="context.myText">';
				},
			)
		);

		register_block_type(
			'test/context-level-with-manual-inner-block-rendering',
			array(
				'render_callback' => function ( $attributes, $content, $block ) {
					$inner_blocks_html = '';
					foreach ( $block->inner_blocks as $inner_block ) {
						$inner_blocks_html .= $inner_block->render();
					}
					return '<div data-wp-interactive=\'{ "namespace": "test" }\' data-wp-context=\'{ "myText": "some value" }\'>' . $inner_blocks_html . '</div>';
				},
				'supports'        => array(
					'interactivity' => true,
				),
			)
		);

		register_block_type(
			'test/directives-ordering',
			array(
				'render_callback' => function () {
					return '<input data-wp-interactive=\'{ "namespace": "test" }\' data-wp-context=\'{ "isClass": true, "value": "some-value", "display": "none" }\' data-wp-bind--value="context.value" class="other-class" data-wp-class--some-class="context.isClass" data-wp-style--display="context.display">';
				},
				'supports'        => array(
					'interactivity' => true,
				),
			)
		);
	}

	public function tear_down() {
		unregister_block_type( 'test/context-level-1' );
		unregister_block_type( 'test/context-level-2' );
		unregister_block_type( 'test/context-read-only' );
		unregister_block_type( 'test/non-interactive-with-directive' );
		unregister_block_type( 'test/context-level-with-manual-inner-block-rendering' );
		unregister_block_type( 'test/directives-ordering' );
		parent::tear_down();
	}

	public function test_interactivity_process_directives_in_root_blocks() {
		$block_content =
		'<!-- wp:paragraph -->' .
			'<p>Welcome to WordPress. This is your first post. Edit or delete it, then start writing!</p>' .
		'<!-- /wp:paragraph -->' .
		'<!-- wp:paragraph -->' .
			'<p>Welcome to WordPress.</p>' .
		'<!-- /wp:paragraph -->';

		$parsed_block        = parse_blocks( $block_content )[0];
		$source_block        = $parsed_block;
		$rendered_content    = render_block( $parsed_block );
		$parsed_block_second = parse_blocks( $block_content )[1];
		$fake_parent_block   = array();

		// Test that root block is intially emtpy.
		$this->assertEmpty( WP_Directive_Processor::$root_block );

		// Test that root block is not added if there is a parent block.
		gutenberg_interactivity_mark_root_blocks( $parsed_block, $source_block, $fake_parent_block );
		$this->assertEmpty( WP_Directive_Processor::$root_block );

		// Test that root block is added if there is no parent block.
		gutenberg_interactivity_mark_root_blocks( $parsed_block, $source_block, null );
		$current_root_block = WP_Directive_Processor::$root_block;
		$this->assertNotEmpty( $current_root_block );

		// Test that a root block is not added if there is already a root block defined.
		gutenberg_interactivity_mark_root_blocks( $parsed_block_second, $source_block, null );
		$this->assertSame( $current_root_block, WP_Directive_Processor::$root_block );

		// Test that root block is removed after processing.
		gutenberg_process_directives_in_root_blocks( $rendered_content, $parsed_block );
		$this->assertEmpty( WP_Directive_Processor::$root_block );
	}

	public function test_directive_processing_of_interactive_block() {
		$post_content    = '<!-- wp:test/context-level-1 /-->';
		$rendered_blocks = do_blocks( $post_content );
		$p               = new WP_HTML_Tag_Processor( $rendered_blocks );
		$p->next_tag( array( 'class_name' => 'level-1-input-1' ) );
		$value = $p->get_attribute( 'value' );
		$this->assertSame( 'level-1', $value );
		$p->next_tag( array( 'class_name' => 'level-1-input-2' ) );
		$value = $p->get_attribute( 'value' );
		$this->assertSame( 'level-1', $value );
	}

	public function test_directive_processing_two_interactive_blocks_at_same_level() {
		$post_content    = '<!-- wp:group --><div class="wp-block-group"><!-- wp:test/context-level-1 /--><!-- wp:test/context-level-2 /--></div><!-- /wp:group -->';
		$rendered_blocks = do_blocks( $post_content );
		$p               = new WP_HTML_Tag_Processor( $rendered_blocks );
		$p->next_tag( array( 'class_name' => 'level-1-input-1' ) );
		$value = $p->get_attribute( 'value' );
		$this->assertSame( 'level-1', $value );
		$p->next_tag( array( 'class_name' => 'level-1-input-2' ) );
		$value = $p->get_attribute( 'value' );
		$this->assertSame( 'level-1', $value );
		$p->next_tag( array( 'class_name' => 'level-2-input-1' ) );
		$value = $p->get_attribute( 'value' );
		$this->assertSame( 'level-2', $value );
	}

	public function test_directives_are_processed_at_tag_end() {
		$post_content    = '<!-- wp:test/context-level-1 --><!-- wp:test/context-level-2 /--><!-- wp:test/context-read-only /--><!-- /wp:test/context-level-1 -->';
		$rendered_blocks = do_blocks( $post_content );
		$p               = new WP_HTML_Tag_Processor( $rendered_blocks );
		$p->next_tag( array( 'class_name' => 'level-1-input-1' ) );
		$value = $p->get_attribute( 'value' );
		$this->assertSame( 'level-1', $value );
		$p->next_tag( array( 'class_name' => 'level-2-input-1' ) );
		$value = $p->get_attribute( 'value' );
		$this->assertSame( 'level-2', $value );
		$p->next_tag( array( 'class_name' => 'read-only-input-1' ) );
		$value = $p->get_attribute( 'value' );
		$this->assertSame( 'level-1', $value );
		$p->next_tag( array( 'class_name' => 'level-1-input-2' ) );
		$value = $p->get_attribute( 'value' );
		$this->assertSame( 'level-1', $value );
	}

	public function test_non_interactive_children_of_interactive_is_rendered() {
		$post_content    = '<!-- wp:test/context-level-1 --><!-- wp:test/context-read-only /--><!-- wp:paragraph --><p>Welcome</p><!-- /wp:paragraph --><!-- /wp:test/context-level-1 -->';
		$rendered_blocks = do_blocks( $post_content );
		$p               = new WP_HTML_Tag_Processor( $rendered_blocks );
		$p->next_tag( array( 'class_name' => 'level-1-input-1' ) );
		$value = $p->get_attribute( 'value' );
		$this->assertSame( 'level-1', $value );
		$p->next_tag( array( 'class_name' => 'read-only-input-1' ) );
		$value = $p->get_attribute( 'value' );
		$this->assertSame( 'level-1', $value );
		$p->next_tag();
		$this->assertSame( 'P', $p->get_tag() );
		$p->next_tag( array( 'class_name' => 'level-1-input-2' ) );
		$value = $p->get_attribute( 'value' );
		$this->assertSame( 'level-1', $value );
	}

	public function test_non_interactive_blocks_are_not_processed() {
		$post_content    = '<!-- wp:test/context-level-1 --><!-- wp:test/non-interactive-with-directive /--><!-- /wp:test/context-level-1 -->';
		$rendered_blocks = do_blocks( $post_content );
		$p               = new WP_HTML_Tag_Processor( $rendered_blocks );
		$p->next_tag( array( 'class_name' => 'non-interactive-with-directive' ) );
		$value = $p->get_attribute( 'value' );
		$this->assertSame( null, $value );
	}

	public function test_non_interactive_blocks_with_manual_inner_block_rendering_are_not_processed() {
		$post_content    = '<!-- wp:test/context-level-with-manual-inner-block-rendering --><!-- wp:test/non-interactive-with-directive /--><!-- /wp:test/context-level-with-manual-inner-block-rendering -->';
		$rendered_blocks = do_blocks( $post_content );
		$p               = new WP_HTML_Tag_Processor( $rendered_blocks );
		$p->next_tag( array( 'class_name' => 'non-interactive-with-directive' ) );
		$value = $p->get_attribute( 'value' );
		$this->assertSame( null, $value );
	}

	public function test_directives_ordering() {
		$post_content    = '<!-- wp:test/directives-ordering -->';
		$rendered_blocks = do_blocks( $post_content );
		$p               = new WP_HTML_Tag_Processor( $rendered_blocks );
		$p->next_tag();

		$value = $p->get_attribute( 'class' );
		$this->assertSame( 'other-class some-class', $value );

		$value = $p->get_attribute( 'value' );
		$this->assertSame( 'some-value', $value );

		$value = $p->get_attribute( 'style' );
		$this->assertSame( 'display: none;', $value );
	}

	public function test_evaluate_function_should_access_state() {
		// Init a simple store.
		wp_store(
			array(
				'state' => array(
					'core' => array(
						'number' => 1,
						'bool'   => true,
						'nested' => array(
							'string' => 'hi',
						),
					),
				),
			)
		);

		$this->assertSame( 1, gutenberg_interactivity_evaluate_reference( 'state.core.number' ) );
		$this->assertTrue( gutenberg_interactivity_evaluate_reference( 'state.core.bool' ) );
		$this->assertSame( 'hi', gutenberg_interactivity_evaluate_reference( 'state.core.nested.string' ) );
		$this->assertFalse( gutenberg_interactivity_evaluate_reference( '!state.core.bool' ) );
	}

	public function test_evaluate_function_should_access_passed_context() {
		$context = array(
			'local' => array(
				'number' => 2,
				'bool'   => false,
				'nested' => array(
					'string' => 'bye',
				),
			),
		);

		$this->assertSame( 2, gutenberg_interactivity_evaluate_reference( 'context.local.number', $context ) );
		$this->assertFalse( gutenberg_interactivity_evaluate_reference( 'context.local.bool', $context ) );
		$this->assertTrue( gutenberg_interactivity_evaluate_reference( '!context.local.bool', $context ) );
		$this->assertSame( 'bye', gutenberg_interactivity_evaluate_reference( 'context.local.nested.string', $context ) );

		// Previously defined state is also accessible.
		$this->assertSame( 1, gutenberg_interactivity_evaluate_reference( 'state.core.number' ) );
		$this->assertTrue( gutenberg_interactivity_evaluate_reference( 'state.core.bool' ) );
		$this->assertSame( 'hi', gutenberg_interactivity_evaluate_reference( 'state.core.nested.string' ) );
	}

	public function test_evaluate_function_should_return_null_for_unresolved_paths() {
		$this->assertNull( gutenberg_interactivity_evaluate_reference( 'this.property.doesnt.exist' ) );
	}

	public function test_evaluate_function_should_execute_anonymous_functions() {
		$context = new WP_Directive_Context( array( 'count' => 2 ) );

		wp_store(
			array(
				'state'     => array(
					'count' => 3,
				),
				'selectors' => array(
					'anonymous_function'  => function ( $store ) {
						return $store['state']['count'] + $store['context']['count'];
					},
					// Other types of callables should not be executed.
					'function_name'       => 'gutenberg_test_process_directives_helper_increment',
					'class_method'        => array( $this, 'increment' ),
					'class_static_method' => array( 'Tests_Process_Directives', 'static_increment' ),
				),
			)
		);

		$this->assertSame( 5, gutenberg_interactivity_evaluate_reference( 'selectors.anonymous_function', $context->get_context() ) );
		$this->assertSame(
			'gutenberg_test_process_directives_helper_increment',
			gutenberg_interactivity_evaluate_reference( 'selectors.function_name', $context->get_context() )
		);
		$this->assertSame(
			array( $this, 'increment' ),
			gutenberg_interactivity_evaluate_reference( 'selectors.class_method', $context->get_context() )
		);
		$this->assertSame(
			array( 'Tests_Process_Directives', 'static_increment' ),
			gutenberg_interactivity_evaluate_reference( 'selectors.class_static_method', $context->get_context() )
		);
	}
}
