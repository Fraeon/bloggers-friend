<?php
/*
 Plugin Name: Blogger's Friend
 Description: Adding products with descriptions
 Author: Blogger's Friend team
 Version: 0.1
 Author URI: lookby.fi
 Text Domain: bloggersfriend
 */

//Required Services_JSON by Michal Migurski
if ( ! function_exists( 'json_encode' ) ) {
    require_once dirname( __FILE__ ) . '/Services_JSON/JSON.php';
    function json_encode( $page_content, $association = false ) {
        $json = new Amazonjs_JSON( ($association) ? AMAZONJS_JSON_LOOSE_TYPE : 0 );
        return $json->encode( $page_content );
    }
}

class bloggersfriend
{
	public $plugin_name;
	public $weblink;
	public $settings;

	//Options for pagenames and settings
	public $o_pagename;
	public $o_name;
	public $o_url;

	//Settings
	public $s_sections;
	public $s_field;
	public $s_template;

	//Misc
	public $p_file;
	public $defaults;
	public $txtdom;
	public $m_type = 'bloggersfriend';
	public $countries;
	public $i_display = array();
	public $index_search;

	const VER        = '0.1';
	const AWS_VER    = '2015-08-02';

	function __construct() {
		$path = __FILE__;
		$dir  = dirname( $path );
		$plugin_path = basename( $dir );
		$this->plugin_name = 'Blogger\'s Friend';
		$this->p_file = basename( $dir ) . DIRECTORY_SEPARATOR . basename( $path );
		$this->o_pagename = basename( $dir );
		$this->o_name = preg_replace( '/[\-\.]/', '_', $this->o_pagename ) . '_settings';
		$this->o_url = admin_url() . 'options-general.php?page=' . $this->o_pagename;
        $this->weblink = plugins_url( '', $path );
		$this->txtdom = $plugin_path;
		load_plugin_textdomain( $this->txtdom, false, dirname( $this->p_file ) . '/languages' );
		$this->countries = array(
			'US' => array(
				'label'              => __( 'US', $this->txtdom ),
				'domain'             => 'amazon.com',
				'baseUri'            => 'http://webservices.amazon.com',
				'linkTemplate'       => '<iframe src="http://rcm.amazon.com/e/cm?t=${t}&o=1&p=8&l=as1&asins=${asins}&fc1=${fc1}&IS2=${IS2}&lt1=${lt1}&m=amazon&lc1=${lc1}&bc1=${bc1}&bg1=${bg1}&f=ifr" style="width:120px;height:240px;" scrolling="no" marginwidth="0" marginheight="0" frameborder="0"></iframe>',
				'associateTagSuffix' => '-20',
			),
			'UK' => array(
				'label'              => __( 'UK', $this->txtdom ),
				'domain'             => 'amazon.co.uk',
				'baseUri'            => 'http://webservices.amazon.co.uk',
				'linkTemplate'       => '<iframe src="http://rcm-uk.amazon.co.uk/e/cm?t=${t}&o=2&p=8&l=as1&asins=${asins}&fc1=${fc1}&IS2=${IS2}&lt1=${lt1}&m=amazon&lc1=${lc1}&bc1=${bc1}&bg1=${bg1}&f=ifr" style="width:120px;height:240px;" scrolling="no" marginwidth="0" marginheight="0" frameborder="0"></iframe>',
				'associateTagSuffix' => '-21',
			),
			'DE' => array(
				'label'              => __( 'DE', $this->txtdom ),
				'domain'             => 'amazon.de',
				'baseUri'            => 'http://webservices.amazon.de',
				'linkTemplate'       => '<iframe src="http://rcm-de.amazon.de/e/cm?t=${t}&o=3&p=8&l=as1&asins=${asins}&fc1=${fc1}&IS2=${IS2}&lt1=${lt1}&m=amazon&lc1=${lc1}&bc1=${bc1}&bg1=${bg1}&f=ifr" style="width:120px;height:240px;" scrolling="no" marginwidth="0" marginheight="0" frameborder="0"></iframe>',
				'associateTagSuffix' => '04-21',
			),
		);
	}

	function clean() {
		$this->delete_settings();
	}

	function init() {
		$this->init_settings();

		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
		}
		add_shortcode( 'bloggersfriend', array( $this, 'shortcode' ) );
		if ( ! is_admin() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_styles' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
			add_action( 'wp_footer', array( $this, 'wp_enqueue_scripts_for_footer' ), 1 );
		}
	}

	function admin_init() {
		add_action( 'media_buttons', array( $this, 'media_buttons' ), 20 );
		add_action( 'media_upload_bloggersfriend', array( $this, 'media_upload_bloggersfriend' ) );
		add_action( 'media_upload_bloggersfriend_keyword', array( $this, 'media_upload_bloggersfriend_keyword' ) );
		add_action( 'media_upload_bloggersfriend_id', array( $this, 'media_upload_bloggersfriend_id' ) );
		add_action( 'wp_ajax_bloggersfriend_search', array( $this, 'ajax_bloggersfriend_search' ) );

		$page = $this->o_pagename;
		register_setting( $this->o_name, $this->o_name, array( $this, 'validate_settings' ) );
		if ( $this->s_sections ) {
			foreach ($this->s_sections as $key => $section ) {
				add_settings_section( $page . '_' . $key, $section['label'], array( $this, $section['add'] ), $page );
			}
		}
		foreach ($this->s_field as $key => $field ) {
			$label = ('checkbox' == $field['type']) ? '' : $field['label'];
			add_settings_field(
				$this->o_name . '_' . $key,
				$label,
				array( $this, 'add_settings_field' ),
				$page,
				$page . '_' . $field['section'],
				array( $key, $field )
			);
		}
	}

	function admin_print_styles() {
		global $wp_version;
		// use dashicon
		if ( version_compare( $wp_version, '3.8', '>=' ) ) {
			wp_enqueue_style( 'bloggersfriend-options', $this->weblink . '/css/bloggersfriend-options.css', array(), self::VER );
		}
	}

	function wp_enqueue_styles() {
		if ( $this->settings['displayCustomerReview'] ) {
			wp_enqueue_style( 'thickbox' );
		}

		if ( $this->settings['overrideThemeCss'] ) {
			wp_enqueue_style( 'bloggersfriend', $this->weblink . '/css/bloggersfriend-force.css', array(), self::VER );
		} else {
			wp_enqueue_style( 'bloggersfriend', $this->weblink . '/css/bloggersfriend.css', array(), self::VER );
		}
		if ( $this->settings['customCss'] ) {
			wp_enqueue_style( 'bloggersfriend-custom', get_stylesheet_directory_uri() . '/bloggersfriend.css' );
		}
	}

	function wp_enqueue_scripts() {
		wp_register_script( 'jquery-tmpl', $this->weblink . '/components/js/jquery-tmpl/jquery.tmpl.min.js', array( 'jquery' ), '1.0.0pre', true );

		$depends = array( 'jquery-tmpl' );
		if ( $this->settings['displayCustomerReview'] ) {
			$depends[] = 'thickbox';
		}
		wp_register_script( 'bloggersfriend', $this->weblink . '/js/bloggersfriend.js', $depends, self::VER, true );
		if ( $this->settings['customJs'] ) {
			wp_register_script( 'bloggersfriend-custom', get_stylesheet_directory_uri() . '/bloggersfriend.js', array( 'bloggersfriend' ), self::VER, true );
		}
	}

	function wp_enqueue_scripts_for_footer() {
		$country_codes = array();
		$items         = array();
		foreach ($this->i_display as $country_code => $sub_items ) {
			foreach ( $this->fetch_items( $country_code, $sub_items ) as $asin => $item ) {
				$items[ $country_code . ':' . $asin ] = $item;
			}
			$country_codes[] = $country_code;
		}

		if ( count( $items ) == 0 ) {
			return;
		}

		$this->enqueue_bloggersfriend_scripts( $items, $country_codes );
	}

	function enqueue_bloggersfriend_scripts( $items = array(), $country_codes = array() ) {
		$wpurl = get_bloginfo( 'wpurl' );

		$region = array();
		foreach ($this->countries as $code => $value ) {
			if ( in_array( $code, $country_codes ) ) {
				foreach ( array( 'linkTemplate' ) as $attr ) {
					$region[ 'Link' . $code ] = $this->tmpl( $value[ $attr ], array( 't' => $this->settings[ 'associateTag' . $code ] ) );
				}
			}
		}

		$amazonVars = array(
			'thickboxUrl'             => $wpurl . '/wp-includes/js/thickbox/',
			'regionTemplate'          => $region,
			'resource'                => array(
				'NumberOfPagesValue'  => __( '${NumberOfPages} pages', $this->txtdom ),
				'Price'               => __( 'Price', $this->txtdom ),
				'ListPrice'           => __( 'List Price', $this->txtdom ),
				'PublicationDate'     => __( 'Publication Date', $this->txtdom ),
				'RunningTimeValue'    => __( '${RunningTime} minutes', $this->txtdom ),
				'ReleaseDate'         => __( 'Release Date', $this->txtdom ),
				'RunningTime'         => __( 'Run Time', $this->txtdom ),
				'CustomerReviewTitle' => __( '${Title} Customer Review', $this->txtdom ),
				'SeeCustomerReviews'  => __( 'See Customer Reviews', $this->txtdom ),
				'PriceUpdatedat'      => __( '(at ${UpdatedDate})', $this->txtdom ),
				'EditorialReview'	  => __('${EditorialReview}', $this->txtdom ),
				'BookAuthor'          => __( 'Author', $this->txtdom ),
				'BookPublicationDate' => __( 'PublicationDate', $this->txtdom ),
				'BookPublisher'       => __( 'Publisher', $this->txtdom ),
			),
			'isCustomerReviewEnabled' => ($this->settings['displayCustomerReview']) ? true : false,
			'isTrackEventEnabled'     => ($this->settings['useTrackEvent']) ? true : false,
			'isFadeInEnabled'         => ($this->settings['useAnimation']) ? true : false,
			'items'                   => array_values( $items ),

		);
		wp_localize_script( 'bloggersfriend', 'bloggersfriendVars', $amazonVars );

		wp_enqueue_script( 'bloggersfriend' );
		if ( $this->settings['customJs'] ) {
			wp_enqueue_script( 'bloggersfriend-custom' );
		}
	}

	function init_settings() {
		// section
		$this->s_sections = array(
			'awsapi'        => array(
				'label' => __( 'Amazon API', $this->txtdom ),
				'add'   => 'add_awsapi_setting_section',
			),
			'custom'  => array(
				'label' => __( 'Customization', $this->txtdom ),
				'add'   => 'add_custom_setting_section',
			),
		);
		// filed
		$template_url         = get_bloginfo( 'template_url' );
		$this->s_field = array(
			'idAccessKey'               => array(
				'label'   => __( 'AWS Access Key ID', $this->txtdom ),
				'type'    => 'text',
				'size'    => 50,
				'section' => 'awsapi',
			),
			'idAccessSecret'           => array(
				'label'   => __( 'AWS Secret Access Key', $this->txtdom ),
				'type'    => 'text',
				'size'    => 50,
				'section' => 'awsapi',
			),
			'customCss'                 => array(
				'label'       => __( 'Custom CSS', $this->txtdom ),
				'type'        => 'checkbox',
				'section'     => 'custom',
				'description' => '(' . $template_url . '/bloggersfriend.css)',
			),
			'customJs'                  => array(
				'label'       => __( 'Custom JS', $this->txtdom ),
				'type'        => 'checkbox',
				'section'     => 'custom',
				'description' => '(' . $template_url . '/bloggersfriend.js)',
			),
		);
		foreach ($this->countries as $key => $value ) {
			$this->s_field[ 'associateTag' . $key ] = array(
				'label'       => __( $value['domain'], $this->txtdom ),
				'type'        => 'text',
				'size'        => 30,
				'placeholder' => 'associatetag' . $value['associateTagSuffix'],
				'section'     => 'associate',
			);
		}

		$this->defaults = array();
		if ( is_array( $this->s_field ) ) {
			foreach ($this->s_field as $key => $field ) {
				$this->defaults[ $key ] = @$field['defaults'];
			}
		}
		//delete_option($this->option_name);
		$this->settings = wp_parse_args( (array) get_option( $this->o_name ), $this->defaults );
	}

	function delete_settings() {
		delete_option( $this->o_name );
	}

	function validate_settings( $settings ) {
		foreach ($this->s_field as $key => $field ) {
			if ( 'checkbox' == $field['type'] ) {
				$settings[ $key ] = ( 'on' == @$settings[ $key ] || '1' == @$settings[ $key ] );
			}
		}

		foreach ( array( 'idAccessKey', 'idAccessSecret' ) as $key ) {
			$settings[ $key ] = trim( $settings[ $key ] );
		}

		foreach ($this->countries as $country_code => $value ) {
			$key            = 'associateTag' . $country_code;
			$settings[ $key ] = trim( $settings[ $key ] );
		}

		return $settings;
	}

	function admin_menu() {
		if ( function_exists( 'add_options_page' ) ) {
			$page_hook_suffix = add_options_page(
				__( $this->plugin_name, $this->txtdom ),
				__( $this->plugin_name, $this->txtdom ),
				'manage_options',
				$this->o_pagename,
				array( $this, 'options_page' )
			);
			add_action( 'admin_print_styles-' . $page_hook_suffix, array( $this, 'admin_print_styles' ) );
		}
	}

	function get_amazon_official_link( $asin, $country_code ) {
		$tmpl = $this->countries[ $country_code ]['linkTemplate'];
		$item = array(
			't'     => $this->settings[ 'associateTag' . $country_code ],
			'asins' => $asin,
			'fc1'   => '000000',
			'lc1'   => '0000FF',
			'bc1'   => '000000',
			'bg1'   => 'FFFFFF',
			'IS2'   => 1,
			'lt1'   => '_blank',
			'f'     => 'ifr',
			'm'     => 'amazon',
		);
		return $this->tmpl( $tmpl, $item );
	}

	function shortcode( $atts, /** @noinspection PhpUnusedParameterInspection */ $content ) {
		/**
		 * @var string $asin
		 * @var string $tmpl
		 * @var string $locale
		 * @var string $title
		 * @var string $imgsize
		 */
		$defaults = array( 'asin' => '', 'tmpl' => '', 'locale' => $this->default_country_code(), 'title' => '', 'imgsize' => '' );
		extract( shortcode_atts( $defaults, $atts ) );
		if ( empty($asin) ) {
			return '';
		}
		$country_code  = strtoupper( $locale );
		$imgsize = strtolower( $imgsize );
		if ( is_feed() ) {
			// use static html for rss reader
			if ( $ai = $this->get_item( $country_code, $asin ) ) {
				$aimg = $ai['SmallImage'];
				if ( array_key_exists( 'MediumImage', $ai ) ) {
					$aimg = $ai['MediumImage'];
				}
				return <<<EOF
<a href="{$ai['DetailPageURL']}" title="{$ai['Title']}" target="_blank">
<img src="{$aimg['src']}" width="{$aimg['width']}" height="{$aimg['height']}" alt="{$ai['Title']}"/>
{$ai['Title']}
</a>
EOF;
			}
			return $this->get_amazon_official_link( $asin, $country_code );
		}
		if ( ! isset($this->i_display[ $country_code ]) ) {
			$this->i_display[ $country_code ] = array();
		}
		$item = (array_key_exists( $asin, $this->i_display[ $country_code ] ))
			? $this->i_display[ $country_code ][ $asin ]
			: $this->i_display[ $country_code ][ $asin ] =  get_site_transient("bloggersfriend_{$country_code}_{$asin}");
		$url  = '#';
		if ( is_array( $item ) && array_key_exists( 'DetailPageURL', $item ) ) {
			$url = $item['DetailPageURL'];
		}
		$indicator_html = <<<EOF
<div data-role="bloggersfriend" data-asin="{$asin}" data-locale="{$country_code}" data-tmpl="${tmpl}" data-img-size="${imgsize}" class="asin_{$asin}_{$country_code}_${tmpl} bloggersfriend_item"><div class="bloggersfriend_indicator"><span class="bloggersfriend_indicator_img"></span><a class="bloggersfriend_indicator_title" href="{$url}">{$title}</a><span class="bloggersfriend_indicator_footer"></span></div></div>
EOF;

		$indicator_html = trim( $indicator_html );
		if ( ! $this->settings['supportDisabledJavascript'] ) {
			return $indicator_html;
		}
		$indicator_html = addslashes( $indicator_html );
		$link_html      = $this->get_amazon_official_link( $asin, $country_code );

		return <<<EOF
<script type="text/javascript">document.write("{$indicator_html}")</script><noscript>{$link_html}</noscript>
EOF;
	}

	/**
	 * Gets default country code by get_locale
	 * @return string
	 */
	function default_country_code() {
		switch ( get_locale() ) {
			case 'en_CA':
				return 'CA';
			case 'de_DE':
				return 'DE';
			case 'fr_FR':
				return 'FR';
			case 'ja':
				return 'JP';
			case 'en_GB':
				return 'UK';
			case 'zh_CN':
				return 'CN';
			case 'it_IT':
				return 'IT';
			case 'es_ES':
				return 'ES';
		}
		return 'US';
	}

	function get_item( $country_code, $asin ) {
		if ( $ai = get_site_transient( "bloggersfriend_{$country_code}_{$asin}" ) ) {
			return $ai;
		}
		$items = $this->fetch_items( $country_code, array( $asin => false ) );
		return @$items[ $asin ];
	}
    
	function fetch_items( $country_code, $items ) {
		$now     = time();
		$item_ids = array();
		foreach ( $items as $asin => $item ) {
			if ( ! $item && $item['UpdatedAt'] + 86400 < $now ) {
				$item_ids[] = $asin;
			}
		}
		while ( count( $item_ids ) ) {
			// fetch via 10 products
			// ItemLookup ItemId: Must be a valid item ID. For more than one ID, use a comma-separated list of up to ten IDs.
			$itemid  = implode( ',', array_splice( $item_ids, 0, 10 ) );
			$results = $this->itemlookup( $country_code, $itemid );
			if ( $results && $results['success'] ) {
				foreach ( $results['items'] as $item ) {
					$items[ $item['ASIN'] ] = $item;
					set_site_transient("bloggersfriend_{$country_code}_{$item['ASIN']}", $item, 100000);
				}
			}
		}
		return $items;
	}

	function tmpl( $tmpl, $item ) {
		$s = $tmpl;
		foreach ( $item as $key => $value ) {
			$s = str_replace( '${' . $key . '}', $value, $s );
		}
		return $s;
	}

	function plugin_row_meta( $links, $file ) {
		if ( $file == $this->p_file ) {
			array_push(
				$links,
				sprintf( '<a href="%s">%s</a>', $this->o_url, __( 'Settings' ) )
			);
			array_push(
				$links,
				sprintf( '', __( '', $this->txtdom ) )
			);
		}
		return $links;
	}

	function add_awsapi_setting_section() {
	}

	function add_custom_setting_section() {
	}

	function add_settings_field( $args = array() ) {
		// not work wordpress 2.9.0 #11143
		if ( empty($args) ) {
			return;
		}
		list ($key, $field) = $args;
		$id    = $this->o_name . '_' . $key;
		$name  = $this->o_name . "[{$key}]";
		$value = $this->settings[ $key ];
		if ( isset($field['html']) ) {
			echo '' . $field['html'] . '';
		} else {
			switch ( $field['type'] ) {
				case 'checkbox':
					?>
					<input id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" type="checkbox" <?php checked( true, $value ); ?> value="1" />
					<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
					<?php
					break;
				case 'radio':
					$index = 1;
					foreach ( $field['options'] as $v => $content ) {
						$input_element_id = $id . '_' . $index;
						?>
						<input id="<?php echo esc_attr( $input_element_id ); ?>" name="<?php echo esc_attr( $name ); ?>" type="radio" <?php checked( $v, $value ); ?> value="<?php echo esc_attr( $v ); ?>" />
						<label for="<?php echo esc_attr( $input_element_id ); ?>"><?php echo esc_html( $content ); ?></label>
						<?php
						$index++;
					}
					break;
				case 'select':
					?>
					<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
					<?php foreach ( $field['options'] as $option => $name ) : ?>
						<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $option, $value ); ?>><?php echo esc_html( $name ); ?></option>
					<?php endforeach ?>
					</select>
					<?php
					break;
				case 'text':
				default:
					$size        = @$field['size'];
					$placeholder = @$field['placeholder'];
					if ( $size <= 0 ) {
						$size = 40;
					}
					if ( ! is_string( $placeholder ) ) {
						$placeholder = '';
					}
					?>
					<input id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" type="text" size="<?php echo esc_attr( $size ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>"/>
					<?php
					break;
			}
		}
		if ( @$field['description'] ) {
			echo '<p class="description">' . $field['description'] . '</p>';
		}
	}

	function media_buttons() {
		global $post_ID, $temp_ID;
		$iframe_ID  = (int) ( 0 == $post_ID ? $temp_ID : $post_ID );
		$iframe_src = 'media-upload.php?post_id=' . $iframe_ID . '&amp;type=' . $this->m_type . '&amp;tab=' . $this->m_type . '_keyword';
		$label      = __( 'Add product description', $this->txtdom );
		?>
		<a href="<?php echo esc_attr( $iframe_src . '&amp;TB_iframe=true' ); ?>" id="add_amazon" class="button thickbox" title="<?php echo esc_attr( $label ); ?>"><img src="<?php echo esc_attr( $this->weblink . '/images/bloggersfriend.png' ); ?>" alt="<?php echo esc_attr( $label ); ?>"/> Blogger's Friend</a>
	<?php
	}

	function media_upload_init() {
		add_action( 'admin_print_styles', array( $this, 'wp_enqueue_styles' ) );

		$this->wp_enqueue_scripts();
		wp_enqueue_style( 'bloggersfriend-media-upload', $this->weblink . '/css/media-upload-type-bloggersfriend.css', array( 'bloggersfriend' ), self::VER );

		$this->enqueue_bloggersfriend_scripts();
	}

	function media_upload_bloggersfriend() {
		$this->media_upload_init();
		wp_iframe( 'media_upload_type_bloggersfriend' );
	}

	function media_upload_bloggersfriend_keyword() {
		$this->media_upload_init();
		wp_iframe( 'media_upload_type_bloggersfriend_keyword' );
	}

	function media_upload_bloggersfriend_id() {
		$this->media_upload_init();
		wp_iframe( 'media_upload_type_bloggersfriend_id' );
	}

	function media_upload_tabs( /** @noinspection PhpUnusedParameterInspection */$tabs ) {
		return array(
			$this->m_type . '_keyword' => __( 'Input URL', $this->txtdom )
			//$this->media_type . '_id'      => __( 'Search by ASIN/URL', $this->text_domain ),
		);
	}

	function options_page() {
		?>
		<div class="wrap wrap-bloggersfriend">
			<h2><?php echo esc_html( $this->plugin_name ); ?></h2>
			<?php $this->options_page_header(); ?>
			<!--suppress HtmlUnknownTarget -->
			<form action="options.php" method="post">
				<?php settings_fields( $this->o_name ); ?>
				<?php do_settings_sections( $this->o_pagename ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
	<?php
	}

	function options_page_header() {
		?>
		<?php if ( ! function_exists( 'simplexml_load_string' ) ) : ?>
			<div class="error">
				<p><?php printf( __( 'Error! "simplexml_load_string" function is not found. %s requires PHP 5 and SimpleXML extension.', $this->txtdom ), $this->plugin_name ); ?></p>
			</div>
		<?php endif ?>
	<?php
	}

	// amazon api
	function itemlookup( $countryCode, $itemId ) {
		$options              = array();
		$options['ItemId']    = $itemId;
		$options['Operation'] = 'ItemLookup';
		return $this->amazon_get( $countryCode, $options );
	}

	// amazon api
	function itemsearch( $countryCode, $searchIndex, $keywords, $itemPage = 0 ) {
		$options = array();
		if ( $itemPage > 0 ) {
			$options['ItemPage'] = $itemPage;
		}
		$options['Keywords']  = $keywords;
		$options['Operation'] = 'ItemSearch';
		if ( $searchIndex ) {
			$options['SearchIndex'] = $searchIndex;
		}
		return $this->amazon_get( $countryCode, $options );
	}

	/**
	 * parse ASIN from URL
	 * @param string $url
	 * @return bool|string
	 */
	static function parse_asin( $url ) {
		if ( preg_match( '/^https?:\/\/.+\.amazon\.([^\/]+).+\/(dp|gp\/product|ASIN)\/([^\/]+)/', $url, $matches ) ) {
			return $matches[3];
		}
		return null;
	}

	function ajax_bloggersfriend_search() {
		$itemId = null;

		// from http get
		$itemPage    = @$_GET['ItemPage'];
		$id          = @$_GET['ID'];
		$keywords    = @$_GET['Keywords'];
		$searchIndex = @$_GET['SearchIndex'];
		$countryCode = @$_GET['CountryCode'];

		if ( ! empty($id) ) {
			if ( preg_match( '/^https?:\/\//', $id ) ) {
				if ( $asin = self::parse_asin( $id ) ) {
					$itemId = $asin;
				} else {
					// url string as query keyword
					$keywords = $id;
				}
			} else {
				$itemId = $id;
			}
		} else if ( ! empty($keywords) ) {
			if ( preg_match( '/^https?:\/\//', $keywords ) ) {
				if ( $asin = self::parse_asin( $keywords ) ) {
					$itemId = $asin;
				}
			}
		}
		$bloggersfriend = new bloggersfriend();
		$bloggersfriend->init();
		if ( isset($itemId) ) {
			$result = $bloggersfriend->itemlookup( $countryCode, $itemId );
			die(json_encode( $result ));
		} else {
			$result = $bloggersfriend->itemsearch( $countryCode, $searchIndex, $keywords, $itemPage );
			die(json_encode( $result ));
		}
	}

	function amazon_get( $countryCode, $options ) {
		$baseUri         = $this->countries[ $countryCode ]['baseUri'];
		$idAccessKey     = @trim( $this->settings['idAccessKey'] );
		$idAccessSecret = @trim( $this->settings['idAccessSecret'] );
		$associateTag    = @$this->settings[ 'associateTag' . $countryCode ];

		// validate request
		if ( empty($countryCode) || (empty($options['ItemId']) && empty($options['Keywords'])) || (empty($idAccessKey) || empty($idAccessSecret)) ) {
			$message = __( 'Invalid Request Parameters', $this->txtdom );
			return compact( 'success', 'message' );
		}

		$options['AWSidAccessKey'] = $idAccessKey;
		if ( ! empty($associateTag) ) {
			$options['AssociateTag'] = @trim( $associateTag );
		}
		$options['ResponseGroup'] = 'ItemAttributes,Small,Images,OfferSummary,SalesRank,Reviews';
		$options['Service']       = 'AWSECommerceService';
		$options['Timestamp']     = gmdate( 'Y-m-d\TH:i:s\Z' );
		$options['Version']       = self::AWS_VER;
		ksort( $options );
		$params = array();
		foreach ( $options as $k => $v ) {
			$params[] = $k . '=' . self::urlencode_rfc3986( $v );
		}
		$query = implode( '&', $params );
		$urlInfo = parse_url( $baseUri );
		$signature = sprintf( "GET\n%s\n/onca/xml\n%s", $urlInfo['host'], $query );
		$signature = base64_encode( hash_hmac( 'sha256', $signature, $idAccessSecret, true ) );
		unset($params, $urlInfo);

		$url = sprintf( '%s/onca/xml?%s&Signature=%s', $baseUri, $query, self::urlencode_rfc3986( $signature ) );

		$response = wp_remote_request(
			$url,
			array(
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$error  = '';
			$errors = $response->get_error_messages();
			if ( is_array( $errors ) ) {
				$error = implode( '<br/>', $errors );
			}
			$message = sprintf( __( 'Network Error: %s', $this->txtdom ), $error );
			return compact( 'success', 'message' );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty($body) ) {
			$message = sprintf( __( 'Empty Response from %s', $this->txtdom ), $url );
			return compact( 'success', 'message' );
		}

		$fetchedAt = time();

		$success = false;
		/* @var $xml stdClass */
		$xml = @simplexml_load_string( $body );
		if ( WP_DEBUG ) {
			if ( ! $xml ) {
				error_log( 'bloggersfriend: cannot parse xml: ' . $body );
			}
		}

		if ( $xml ) {
			if ( 'True' == (string) @$xml->Items->Request->IsValid ) {
				$success   = true;
				$items     = array();
				$operation = $options['Operation'];
				if ( 'ItemSearch' == $operation ) {
					$os                 = array(); // OpenSearch
					$request            = $xml->Items->Request->ItemSearchRequest;
					$resultMap          = self::to_array( $xml->Items->SearchResultsMap );
					$itemsParPage       = 10;
					$startPage          = ($request->ItemPage) ? (int) $request->ItemPage : 1;
					$os['itemsPerPage'] = $itemsParPage;
					$os['startIndex']   = ($startPage - 1) * $itemsParPage + 1;
					$os['Query']        = array( 'searchTerms' => (string) $request->Keywords, 'startPage' => $startPage );
				}
				$os['totalResults'] = (int) $xml->Items->TotalResults;
				$os['totalPages']   = (int) $xml->Items->TotalPages;

				foreach ( $xml->Items->Item as $item ) {
					$r                  = self::to_array( $item->ItemAttributes );
					$r['ASIN']          = trim( (string) $item->ASIN );
					$r['DetailPageURL'] = trim( (string) $item->DetailPageURL );
					$r['SalesRank']     = (int) $item->SalesRank;
					if ( $reviews = $item->CustomerReviews ) {
						$r['IFrameReviewURL'] = (string) $reviews->IFrameURL;
					}
					$r['OfferSummary'] = self::to_array( $item->OfferSummary );
					$r['SmallImage']   = self::image_element( $item->SmallImage );
					$r['MediumImage']  = self::image_element( $item->MediumImage );
					$r['LargeImage']   = self::image_element( $item->LargeImage );
					$r['CountryCode']  = $countryCode;
					$r['UpdatedAt']    = $fetchedAt;
					$r['EditorialReview'] = trim( (string) $item->EditorialReview );
					$items[]           = $r;
				}
				if ( 'ItemLookup' == $operation ) {
					if ( 0 == $os['totalResults'] ) {
						$os['totalResults'] = count( $items );
					}
					if ( 0 == $os['totalPages'] ) {
						$os['totalPages'] = 1;
					}
				}
			} else {
				if ( $error = @$xml->Items->Request->Errors->Error ) {
					$message = __( 'Amazon Product Advertising API Error', $this->txtdom );
					$error_code    = (string) @$error->Code;
					$error_message = (string) @$error->Message;
				} elseif ( $error = @$xml->Error ) {
					$message = __( 'Amazon Product Advertising API Error', $this->txtdom );
					$error_code    = (string) @$error->Code;
					$error_message = (string) @$error->Message;
				} else {
					$message    = __( 'Cannot Parse Amazon Product Advertising API Response' );
					$error_body = (string) $body;
				}
			}
		} else {
			$message = __( 'Invalid Response', $this->txtdom );
		}
		return compact( 'success', 'operation', 'os', 'items', 'resultMap', 'message', 'error_code' , 'error_message', 'error_body' );
	}

	static function to_array( $element ) {
		$orgElement = $element;
		if ( is_object( $element ) && 'SimpleXMLElement' == get_class( $element ) ) {
			$element = get_object_vars( $element );
		}
		if ( is_array( $element ) ) {
			$result = array();
			if ( count( $element ) <= 0 ) {
				return trim( strval( $orgElement ) );
			}
			foreach ( $element as $key => $value ) {
				if ( is_string( $key ) && '@attributes' == $key ) {
					continue;
				}
				$result[ $key ] = self::to_array( $value );
			}
			return $result;
		} else {
			return trim( strval( $element ) );
		}
	}

	static function image_element( $element ) {
		if ( $element ) {
			$src    = trim( (string) @$element->URL );
			$width  = (int) @$element->Width;
			$height = (int) @$element->Height;
			return compact( 'src', 'width', 'height' );
		}
		return null;
	}

	static function urlencode_rfc3986( $string ) {
		return str_replace( '%7E', '~', rawurlencode( $string ) );
	}
}

function media_upload_type_bloggersfriend() {
	include dirname( __FILE__ ) . '/bloggersfriend-media.php';
}

function media_upload_type_bloggersfriend_keyword() {
	include dirname( __FILE__ ) . '/bloggersfriend-media.php';
}

function media_upload_type_bloggersfriend_id() {
	include dirname( __FILE__ ) . '/bloggersfriend-media.php';
}

function bloggersfriend_init() {
	global $bloggersfriend;
	$bloggersfriend = new bloggersfriend();
	$bloggersfriend->init();
}

function bloggersfriend_uninstall() {
	$bloggersfriend = new bloggersfriend();
	$bloggersfriend->clean();
	unset($bloggersfriend);
}

function bloggersfriend_aws(bloggersfriend $bloggersfriend ) {
    $bloggersfriend->index_search = array(
        'All'                 => array(
            'DE'    => true,
            'UK'    => true,
            'US'    => true,
            'label' => __( 'All', $bloggersfriend->txtdom ),
        ),
    );
}

add_action( 'init', 'bloggersfriend_init' );
if ( function_exists( 'register_uninstall_hook' ) ) {
	register_uninstall_hook( __FILE__, 'bloggersfriend_uninstall' );
}
