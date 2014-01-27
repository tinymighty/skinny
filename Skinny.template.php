<?php
/**
 * SkinnyTemplate provides a bit of flair to good old BaseTemplate. Use it to create
 * more awesome skins. Use the companion extensions like #movetoskin and #skintemplate 
 * move content from your wikitext to your skin, and to safely render php templates in your 
 * wikitext for easily and safely adding advanced forms, javascript, and so on.
 *
 * It extracts all the usual MediaWiki skin html soup into re-usable template files in the 
 * template directory, and introduces add() and insert() as methods for handling content
 * display.
 *
 * Check out the documentation at http://mediawiki.net/wiki/Extension:Skinny
 */
abstract class SkinnyTemplate extends BaseTemplate {

	//core settings
	protected $_defaults = array(
		'debug' => 'false',
		'mainTemplate' => 'main'
	);

	protected $defaults = array();
	protected $options = array();

	protected $_template_paths = array();

	public function __construct( $options=array() ){
		
		parent::__construct();
		$this->setDefaults( $this->_defaults );
		$this->setOptions( $options );

		//adding path manually ensures that there's an entry for every class in the heirarchy
		//allowing for template fallback to every skin all the way down
		$this->addTemplatePath( dirname(__FILE__).'/templates' );
		

	}

	public function setDefaults($defaults){
		$this->defaults = array_merge( $this->defaults, $defaults ); 
	}
	public function setOptions($options){
		if( empty($this->options) ){
			$this->options = $this->defaults;
		}
		foreach($this->defaults as $k => $v){
			if( isset($options[$k]) ){
				if( is_array($v) ){
					//allow option=true/false as a shortcut for option=[enabled=>true/false]
					if( is_bool($options[$k]) && isset($v['enabled']) ){
						$options[$k] = array('enabled'=>$options[$k]);
					}

					$this->options[$k] = array_merge_recursive( $this->defaults[$k], $options[$k] );
					
				}else{
					$this->options[$k] = $options[$k];
				}
				
			}
		}
	}

	protected function addTemplatePath($path){
		if(file_exists($path) && is_dir($path)){
			array_unshift( $this->_template_paths, $path);
		}
	}

	/**
	 * The place to initialize all content areas. Overwrite this in your skin.
	 */
	abstract protected function initialize();

	/**
	 * This is called by MediaWiki to render the skin.
	 */
	final function execute() {
		$this->initialize();
		$this->_preExecute();
		$this->data['pageLanguage'] = $this->getSkin()->getTitle()->getPageViewLanguage()->getCode();
		echo $this->renderTemplate($this->options['mainTemplate']);

	}

	/**/
	protected function _preExecute(){

	}

	public function renderTemplate($template, $args=array()){
		ob_start();
		extract($args);
		if($this->options['debug']===true){
			echo '<div class="skinny-debug">Skinny:Template: '.$template.'</div>';
		}
		//try all defined template paths
		foreach($this->_template_paths as $path){
			$filepath = $path.'/'.$template.'.tpl.php';
			if( file_exists($filepath) ){
				require( $filepath );
				break; //once we've rendered a template, stop traversing template_paths
			}
		}
		return ob_get_clean();
	}

	/**
	 * Add the result of a function callback to a zone
	 *
	 * 	eg.	add('before:content', array('methodName', $obj))
	 *			add('before:content', 'methodOnThisObject')
	 */
	public function addHook($zone, $hook, $args=array()){
		if(!isset($this->content[$zone])){
			$this->content[$zone] = array();
		}
		//allow just a string reference to a method on this skin object
		if(!is_array($hook) && method_exists($this, $hook)){
			$hook = array($hook, $this);
		}else{
			return false;
		}
		$this->content[$zone][] = array('type'=>'hook', 'hook'=>$hook, 'arguments'=>$args);
	}

	/**
	 * Render the output of a template to a zone
	 *
	 * eg.  add('before:content', 'template-name')
	 */
	public function addTemplate($zone, $template, $params=array()){
		if(!isset($this->content[$zone]))
			$this->content[$zone] = array();
		$this->content[$zone][] = array('type'=>'template', 'template'=>$template, 'params'=>$params);
	}

	/**
	 * Add html content to a zone
	 *
	 * eg.  add('before:content', '<h2>Some html content</h2>')
	 */
	public function addHTML($zone, $content){
		if(!isset($this->content[$zone]))
			$this->content[$zone] = array();
		$this->content[$zone][] = array('type'=>'html', 'html'=>$content);
	}

	/**
	 * Add a zone to a zone. Allows adding zones without editing template files.
	 *
	 * eg.  add('before:content', 'zone name')
	 */
	public function addZone($zone, $name, $params=array()){
		if(!isset($this->content[$zone]))
			$this->content[$zone] = array();
		$this->content[$zone][] = array('type'=>'zone', 'zone'=>$name, 'params'=>$params);
	}

	/**
	 * Convenience template method for <?php echo $this->render() ?>
	 */
	function insert($zone, $args=array()){
		echo $this->render($zone, $args);
	}
	function before($zone, $args=array()){
		$this->insert('before:'.$zone, $args);
	}
	function after($zone, $args=array()){
		$this->insert('after:'.$zone, $args);
	}
	function prepend($zone, $args=array()){
		$this->insert('prepend:'.$zone, $args);
	}
	function append($zone, $args=array()){
		$this->insert('append:'.$zone, $args);
	}

	/**
	 * Run content content, optionally passing arguments to provide to
	 * object methods
	 */
	protected function render($zone, $args=array()){
		$sep = isset($args['seperator']) ? $args['seperator'] : ' ';

		$content = '';
		if(isset($this->content[$zone])){
			foreach($this->content[$zone] as $item){
				if($this->options['debug']===true){
					$content.='<!--Skinny:Template: '.$template.'-->';
				}
				switch($item['type']){
					case 'hook':
						//method will be called with two arrays as arguments
						//the first is the args passed to this method (ie. in a template call to $this->insert() )
						//the second are the args passed when the hook was bound
						$content .= call_user_method_array($item['hook'][0], $item['hook'][1], array($args, $item['arguments']));
						break;
					case 'html':
						$content .= $sep . (string) $item['html'];
						break;
					case 'template':
						$content .= $this->renderTemplate($item['template'], $item['params']);
						break;
					case 'zone':
						$content .= $this->render($item['zone'], $item['params']);
						break;
				}	

			}
		}
		//content from #movetoskin and #skintemplate
		if( Skinny::hasContent($zone) ){
			foreach(Skinny::getContent($zone) as $item){

				//pre-rendered html from #movetoskin
				if(isset($item['html'])){
					if($this->options['debug']===true){
						$content.='<!--Skinny:MoveToSkin: '.$template.'-->';
					}
					$content .= $sep . $item['html'];
				}
				else
				//a template name to render
				if(isset($item['template'])){
					if($this->options['debug']===true){
						$content.='<!--Skinny:Template (via #skintemplate): '.$item['template'].'-->';
					}
					$content .= $this->renderTemplate( $item['template'], $item['params'] );
				}
			}
		}
		return $content;
	}


  //parse the bodytext and insert any templates added by the 
  public function parseContent( $html ){
    $pattern = '~ADDTEMPLATE:([\w_-]+):ETALPMETDDA~m';
    if( preg_match_all($pattern, $html, $matches, PREG_SET_ORDER) ){
      foreach($matches as $match){
        $html = str_replace($match[0], $this->renderTemplate($match[1]), $html);
      }
    }
    return $html;
  }


} // end of class

