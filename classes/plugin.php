<?php
class wps_checker
{
	var $options;
	
	function wps_checker()
	{
		$this->__construct();
	}	
	
	function __construct()
	{
		$this->load_options();
	
		register_activation_hook(__FILE__, array(&$this, 'install'));
		register_deactivation_hook(__FILE__, array(&$this, 'uninstall'));

		add_filter( 'query_vars', array( &$this, 'query_vars' ) );
		add_filter( 'request', array( &$this, 'request' ) );
		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
		
		add_action( SC_WPS_CRON_HOOK, array( &$this, 'cron' ) );
		
		add_action( 'widgets_init', array( &$this, 'widgets_init' ));
		
		add_action( 'admin_notices', array( &$this, 'admin_notices' ) );
		add_filter( 'plugin_action_links', array( &$this, 'plugin_action_links' ), 10, 2 );
		
	}

	function admin_url( $mainpage = 'options-general.php', $subpage = SC_WPS_ADMIN_PAGE )
	{
		if ( $subpage == '' )
		{
			return esc_url_raw( admin_url( $mainpage ) );
		} else {
			return esc_url_raw( admin_url( "$mainpage?page=$subpage" ) );
		}
	}

	//Code to add settings page link to the main plugins page on admin
	function plugin_action_links( $links, $file )
	{
		if ( $file != SC_WPS_PLUGIN_BASENAME )
			return $links;
	
		$url = $this->admin_url();
	
		$settings_link = '<a href="' . esc_attr( $url ) . '">'
			. esc_html( __( 'Settings') ) . '</a>';
	
		array_unshift( $links, $settings_link );
	
		return $links;
	}
	
	function check_common_errors()
	{
		$errors = $this->get_option('process_errors');
		if ( !empty($errors) )
		{
		    echo '<div class="error fade">
		       <p>'.SC_WPS_PLUGIN_NAME. ': <strong>'. __('Se han producido errores en la última operación de proceso de páginas') . '</strong></p>
		       '.implode('<br/>', $errors).'
		    </div>';
		}
		if ( $this->get_option('apikey') != '' )
		{
			return;
		}	
	    echo '<div class="updated fade">
	       <p>'.SC_WPS_PLUGIN_NAME. ': '. sprintf( __('Falta informar la API Key. Puedes hacerlo ahora desde la <a href="%s">página de configuración</a>'), $this->admin_url() ).'</p>
	    </div>';
	}
	
	function admin_notices()
	{
		if ( !empty($_POST) )
		{
			return;
		}
		$this->check_common_errors();
	}
	
	function widgets_init()
	{
		if ( class_exists('WPS_Widget') )
		{
			register_widget('WPS_Widget');
		}	
	}
	
	function cron()
	{
		$hour = (int)date('H', current_time('timestamp') );
		if ( !in_array( $hour, $this->get_option('cron_hours') ) )
		{
			return;
		}
		$this->process();
	}
	
	function install()
	{
		wp_clear_scheduled_hook(SC_WPS_CRON_HOOK);
		if (!wp_next_scheduled(SC_WPS_CRON_HOOK))
		{
			$time = strtotime( date('Y-m-d H:05:00' ) );
			wp_schedule_event($time, 'hourly', SC_WPS_CRON_HOOK);
		}		
	}
	
	function uninstall()
	{
		wp_clear_scheduled_hook(SC_WPS_CRON_NAME);
	}
	
	function load_options()
	{
		$this->options = get_option(SC_WPS_OPTIONS);
		if ( empty($this->options) )
		{
			$this->options=array();
		}

		$value = $this->get_option('email');
		if (empty($value)) { $this->set_option('email',get_bloginfo('admin_email')); }
		
		$value = $this->get_option('urls');
		if (empty($value)) { $this->set_option('urls', array( 
				'/' => array( 'url'=>'/', 'score_limit'=>'85', 'score_obtained'=>0 ),
			) ); }

		$value = $this->get_option('subject');
		if (empty($value)) { $this->set_option('subject', __('Aviso de pérdida de puntuación') . ' - ' . SC_WPS_PLUGIN_NAME ); }

		$value = $this->get_option('cron_hours');
		if (empty($value)) { $this->set_option('cron_hours', array(0,12) ); }

		// Así en el caso de que lo desactiven manualmente no lo reactivamos de forma automática.
		if ( !isset($this->options['promote']))
		{
			$this->set_option('promote', 1 );
		}

		$value = $this->get_option('process_errors');
		if (empty($value)) { $this->set_option('process_errors', array() ); }
	}
	
	function query_vars($public_query_vars)
	{
		$public_query_vars[] = SC_WPS_QUERYVAR_RUNPROCESS;
		return $public_query_vars;
	}

	function request( $values )
	{
		$this->query_vars = $values;
		if ( !empty( $values[ SC_WPS_QUERYVAR_RUNPROCESS ] ) )
		{
			$this->process();
		}		
		return $values;
	}
	
	function process()
	{
		global $apiConfig;

		$apikey = $this->get_option('apikey');
		$apikey = trim( $apikey );
		if ( empty( $apikey ) )
		{
			return;
		}

		$apiConfig['developer_key'] = $this->get_option('apikey');
		$apiConfig['authClass'] = 'apiAuthNone';
	
		$apiClient = new apiClient();
		$apiClient->setApplicationName(SC_WPS_APPLICATION_NAME);
		$service = new apiPagespeedonlineService($apiClient);

		try
		{
			$body='';
			$urls = $this->get_option('urls');
			if ( empty($urls) )
			{
				return;
			}

			$errors = array();
			
			$mainblogurl = get_bloginfo('url');
			foreach( $urls as $url => $data )
			{
				$score = $data['score_limit'];
				
				$url = trailingslashit( $url );
				
				$rules = array();
				$impacts = array();
				$scores = array();
				
				$result = $service->pagespeedapi->runpagespeed($mainblogurl.$url, array('locale'=>SC_WPS_GOOGLE_LOCALE) );

				if ( isset($result['responseCode']) && $result['responseCode'] == 200 )
				{
					//$data['score_obtained'] = intval($result['score']);
					$urls[$url]['score_obtained'] = intval($result['score']); // Lo anterior no funciona
					if ( intval($result['score']) < intval($score) )
					{
						foreach( $result['formattedResults']['ruleResults'] as $ruleResult )
						{
							if ( $ruleResult['ruleScore'] < 100 )
							{
								$rules[] = array(
										'name'=>$ruleResult['localizedRuleName'] . ' - ' . $ruleResult['ruleImpact'] . ' - ' . $ruleResult['ruleScore'] . ' - ',
										'localizedRuleName'=>$ruleResult['localizedRuleName'],
										'ruleImpact'=>$ruleResult['ruleImpact'],
										'ruleScore'=>$ruleResult['ruleScore'],
									);
								$impacts[] = $ruleResult['ruleImpact'];
								$scores[] = $ruleResult['ruleScore'];
							}
						}
						
						$high = array();
						$medium = array();
						
						array_multisort($impacts, SORT_DESC, $scores, SORT_DESC, $rules);

						foreach ( $rules  as $rule )
						{
							if ( $rule['ruleImpact'] >= SC_WPS_HIGH_PRIORITY_LIMIT )
							{
								$high[] = ' - '.$rule['localizedRuleName'];
							}
							elseif ( $rule['ruleImpact'] >= SC_WPS_MEDIUM_PRIORITY_LIMIT )
							{
								$medium[] = ' - '.$rule['localizedRuleName'];
							}
						}

						$body .= sprintf(__('<p>La web <strong>%s</strong> ha perdido el score límite de (%d), la puntuación actual es: %d.</p>') . "\n",
								$url,
								intval($score),
								intval($result['score'])
							);
						if ( !empty($high) )
						{
							$body .= __('Reglas con problemas de <strong>alta prioridad</strong>:<br/>') . "\n";
							$body .= implode("<br/>\n", $high );
							$body .= '<br/>'."\n";
						}
						if ( !empty($medium) )
						{
							$body .= __('Reglas con problemas de prioridad media:<br/>') . "\n";
							$body .= implode("<br/>\n", $medium );
							$body .= '<br/>'."\n";
						}
					}
				} else {
					$errors[] = sprintf( __('Ha ocurrido un error %1$d al enviar la url %2$s sobre la API de PageSpeed. Fecha: %3$s'), $result['responseCode'], $url, current_time('mysql') );
				}
				sleep(1); // Para evitar que Google nos bloquee por peticiones masivas.
			}
			
			$this->set_option('urls',$urls);
			$this->set_option('process_errors', $errors);
			
			if ( !empty($body) )
			{
				$body = sprintf( __('<p>Páginas del blog <strong>%s</strong> con problemas según Google PageSpeed:</p>'), get_bloginfo('url') ). "\n" . $body;
				wp_mail( $this->get_option('email'), $this->get_option('subject'), $body );
			}
			
		} catch (Exception $e) {
		    echo sprintf( __('Ha ocurrido un error: %s'),  $e->getMessage() ), "\n";
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

	function admin_menu()
	{
		add_submenu_page('options-general.php', SC_WPS_PLUGIN_NAME, SC_WPS_PLUGIN_SHORTNAME, 5, SC_WPS_ADMIN_PAGE, array($this,'admin_page_content') );
	}
	
	function admin_page_content()
	{
        if (!current_user_can('manage_options'))
        {
            wp_die( __('You do not have sufficient permissions to access this page.') );
        }
        
        if ( isset($_POST['wps_checknow']) || isset($_POST['wps_save']) )
        {
        	$this->set_option( 'apikey', $_POST['apikey'] );
        	$this->set_option( 'email', $_POST['email'] );
        	$this->set_option( 'promote', isset($_POST['promote']) );
        	$this->set_option( 'cron_hours', $_POST['hours'] );
        	
        	$urls = array();
        	$current_urls = $this->get_option('urls');
        	
        	$posted_urls = trim($_POST['urls']);
        	if (!empty($posted_urls))
        	{
	        	$posted_urls = explode( "\n", $posted_urls );
	        	foreach( $posted_urls as $data )
	        	{
					list($url,$score) = explode(",",$data);
					$url = trailingslashit( $url );
					$item = array( 'url'=>$url, 'score_limit'=>$score, 'score_obtained'=>0 );
					
					if ( isset( $current_urls[$url] ) )
					{
						$item['score_obtained'] = $current_urls[$url]['score_obtained'];
					}
					$urls[$url] = $item;
	        	}
	        }
        	$this->set_option( 'urls', $urls );
        	
        	if ( !empty($_POST['wps_checknow']) )
        	{
        		$this->process();
        	}

			$this->check_common_errors();
			
			if ( isset($_POST['wps_save'] ) )
			{
			    echo '<div class="updated fade">
			       <p><strong>' . __('Configuración guardada correctamente') . '</strong></p>
			    </div>';
			}
			if ( isset($_POST['wps_checknow'] ) )
			{
			    echo '<div class="updated fade">
			       <p><strong>' . __('Se ha completado el proceso de obtención de puntuaciones para las páginas.') . '</strong> <a href="#wps_paginas">' . __('Ver resultados') . '</a></p>
			    </div>';
			}
		}
		        
        $current_scores='';
        
    	$current_urls = '';
    	$urls = $this->get_option('urls');
		foreach( $urls as $url => $data )
		{
			$score = $data['score_limit'];
			$obtained = $data['score_obtained'];
			
			$current_urls .= "$url,$score\n";
			if ( !empty($obtained) )
			{
				$current_score = "$url => $obtained";
				if ( intval($obtained) < intval($score) )
				{
					$current_score = '<span style="'.SC_WPS_BAD_SCORE_STYLE.'">'.$current_score.'</span> ' . sprintf( __('( límite %d )'), $score );
				} elseif ( intval($obtained) > intval($score) ) {
					$current_score = '<span style="'.SC_WPS_GOOD_SCORE_STYLE.'">'.$current_score.'</span> ' . sprintf( __('( límite %d )'), $score );
				} else {
					$current_score = '<span style="'.SC_WPS_EQUAL_SCORE_STYLE.'">'.$current_score.'</span>';
				}
				$current_score .= '<br/>'."\n";
				$current_scores .= $current_score;
			}
		}
		$current_urls = trim($current_urls);
        
?>
<div class="wrap">
<div id="icon-options-general" class="icon32"><br /></div>
<h2><?=SC_WPS_PLUGIN_NAME?></h2>
<form method="post" action="options-general.php?page=<?=SC_WPS_ADMIN_PAGE?>">
<h3><?php _e('Configuración API')?></h3>
<p><?php _e('Para que este plugin funcione correctamente se requiere disponer de una <a target="_blank" href="https://www.google.com/accounts/NewAccount">Cuenta en Google</a> con acceso activo a la API de Page Speed Online.')?></p>
<p><?php _e('Lo segundo es disponer de una API key, para ello se ha de realizar los pasos siguientes:</p>') ?></p>
<ul>
<ol><?php _e('Entrar en la página de <a href="https://code.google.com/apis/console" target="_blank">APIs Console</a>')?></ol>
<ol><?php _e('En el panel Services, activar la opción "Page Speed Online API" mediante el deslizador.')?></ol>
<ol><?php _e('Ir al panel <a target="_blank" href="https://code.google.com/apis/console#access">API Access</a> y en la parte inferior, en la seccion "Simple API Access", veréis la API Key asociada a vuestra cuenta.')?></ol>
</ul>
<br/>
<table class="form-table">
<tr><th scope="row"><?php _e('API Key')?></th><td><input type="text" name="apikey" value="<?= esc_attr($this->get_option('apikey')); ?>"/></td></tr>
</table>
<h3><?php _e('Configuración email aviso')?></h3>
<p><?php _e('Indica la cuenta de correo en la cual quieres recibir las alertas de bajo score en las páginas testeadas.')?></p>
<table class="form-table">
<tr><th scope="row"><?php _e('Email')?></th><td><input type="text" name="email" value="<?= esc_attr($this->get_option('email')); ?>"/></td></tr>
<tr><th scope="row"><?php _e('Asunto')?></th><td><input type="text" name="subject" value="<?= esc_attr($this->get_option('subject')); ?>"/></td></tr>
</table>
<h3><?php _e('Configuración Widget')?></h3>
<table class="form-table">
<tr><td><input type="checkbox" name="promote" value="1" <? if ( $this->get_option('promote') ): ?>checked="checked"<?php endif;?>/> <?php _e('¿Quieres ayudarnos a difundir este plugin?')?> <?php echo sprintf( __('Hemos creado un <a href="%1$s">widget</a> llamado <strong>%2$s</strong> que te permitirá añadir tus puntuaciones en tu blog'), $this->admin_url('widgets.php',''), SC_WPS_PLUGIN_NAME )?></td></tr>
</table>
<h3><?php _e('Páginas dentro del blog para comprobar')?></h3>
<p><?php _e('Debes poner una página por linea y utilizar el formato (<strong>página</strong>)(<strong>coma</strong>)(<strong>score</strong>).')?></p>
<p><?php _e('Ejemplo:')?> </p>
<?php _e('/,90')?><br/>
<?php _e('/fotos,85')?><br/>
<?php _e('/category/compras/,85')?><br/>
<p><?php _e('<strong>Notas</strong>:')?></p>
<?php _e('Las páginas han de empezar con barra ( / ) y son relativas a tu página (no pongas http://)')?><br/>
<?php _e('Score ha de ser un valor entre 0 y 100')?><br/>
<?php _e('Se esperará un segundo entre cada petición de score para las páginas')?><br/>
<?php _e('Solamente se permiten <strong>250 consultas</strong> por dia')?><br/>
<table class="form-table" id="wps_paginas">
<tr><th scope="row"><?php _e('Páginas')?></th>
<td>
<strong><?php bloginfo('url')?></strong><br/>
<textarea rows="5" cols="50" name="urls"><?= esc_attr($current_urls); ?></textarea><br/>
<input class="button-primary" type="submit" method="post" value="<?php _e('Comprobar Ahora')?>" name="wps_checknow" id="wps_checknow"><br/>
<?php if (!empty($current_scores)):?>
<br/><strong><?php _e('Ultima puntuación obtenida:')?></strong><br/>
<?php echo $current_scores ?>
<?php endif;?>
</td></tr>
</table>
<h3><?php _e('Programación')?></h3>
<p><?php _e('Marcamos a qué horas lanzaremos el proceso de comprobación del score. Ten en cuenta para establecer la programación que hay un máximo de 250 consultas permitidas por dia sobre la API.')?></p>
<table class="form-table">
<tr><td>
<?php
$cron_hours = $this->get_option('cron_hours');
for( $x=0;$x<24;$x++)
{
	$checked='';
	if ( in_array($x,$cron_hours) )
	{
		$checked = ' checked="checked"';
	}
?>
<input type="checkbox" name="hours[]" value="<?=$x?>" <?=$checked?> style="margin-left:10px;" /> <?=$x?>
<?php
}
?>
</td></tr>
</table>
<p class="submit"><input class="button-primary" type="submit" method="post" value="<?php _e('Guardar Configuración')?>" name="wps_save"></p>
</form>
</div>
<script type="text/javascript">
if ( jQuery )
{
	jQuery('#wps_checknow').click( function() {
			jQuery(this).val("<?=esc_attr( __('Procesando, espere un momento por favor...') )?>");
		});
}
</script>


<?php
	}
	
    function html($file)
    {
        require_once(SC_WPS_TEMPLATES_DIR.'/'.$file);
    }
}
new wps_checker;
?>