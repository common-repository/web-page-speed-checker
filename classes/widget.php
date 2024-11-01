<?php
class WPS_Widget extends WP_Widget {
	var $options;
	
	function WPS_Widget() {
		$this->load_options();
		$widget_ops = array('description' => __('Muestra el score de PageSpeed en tus páginas') );
		$this->WP_Widget('WPS_Widget', SC_WPS_PLUGIN_NAME, $widget_ops);
	}

	function form($instance)
	{
		global $wpdb;
		$instance = wp_parse_args((array) $instance, array('title' => SC_WPS_PLUGIN_NAME ));
		$title = esc_attr($instance['title']);
		
		if ( empty($instance['content']) )
		{
			$instance['content'] = __('Esta página tiene un valor pagespeed de <strong>%d</strong> sobre 100');
		}
		$content = $instance['content'];
		
?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Título')?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('content'); ?>"><?php _e('Contenido')?> <input class="widefat" id="<?php echo $this->get_field_id('content'); ?>" name="<?php echo $this->get_field_name('content'); ?>" type="text" value="<?php echo $content; ?>" /></label>
		</p>
		<input type="hidden" id="<?php echo $this->get_field_id('submit'); ?>" name="<?php echo $this->get_field_name('submit'); ?>" value="1" />
<?php
	}

	function update($new_instance, $old_instance) {
		if (!isset($new_instance['submit'])) {
			return false;
		}
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['content'] = $new_instance['content'];
		return $instance;
	}

	function widget($args, $instance)
	{
		$current_url = $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];

		$current_url = str_replace(get_bloginfo('url'), '', "http://$current_url" );
		$current_url = trailingslashit( $current_url );

		$body = '';
		
		$urls = $this->get_option('urls');
		foreach( $urls as $url=>$data )
		{			
			$url = trailingslashit( $url );
			if ( $current_url == $url && !empty($data['score_obtained']) )
			{
				$body .= sprintf($instance['content'], $data['score_obtained']);
				break;
			}
		}
		
		if ( empty($body) )
		{
			return;
		}
		
		extract($args);
		$title = apply_filters('widget_title', esc_attr($instance['title']));
		echo $before_widget;
		if(!empty($title)) {
			echo $before_title.$title.$after_title;
		}
		if ( $this->get_option('promote') )
		{
			$body .= sprintf( __('<p>¿Quieres este plugin en tu página? Descárgalo desde la web de <a href="http://www.seocom.es">Seocom.es</a> haciendo clic en este <a href="%s">enlace</a></p>'), SC_WPS_HOME_WEBSITE );
		}
		echo '<div class="wps_wrap">'.$body.'</div>';
		echo $after_widget;
	}

	function load_options()
	{
		$this->options = get_option(SC_WPS_OPTIONS);
		if ( empty($this->options) )
		{
			$this->options=array();
		}
	}

	function set_option( $key, $value )
	{
		$this->options[$key]=$value;
    	update_option(SC_WPS_OPTIONS, $this->options );
	}
	function get_option( $key )
	{
		return $this->options[$key];
	}
}
?>