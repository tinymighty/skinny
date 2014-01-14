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
class SkinnyTemplate extends BaseTemplate {

	protected $settings = array();

	public $options = array();

	protected $_template_paths = array();

	public function __construct($auto_initialize=true){
		
		parent::__construct();

		//set options
		$options = $this->options = array_merge($this->settings, $this->options, Bootstrap::$options);

		//adding path manually ensures that there's an entry for every class in the heirarchy
		//allowing for template fallback to every skin all the way down
		$this->addTemplatePath( dirname(__FILE__).'/templates' );
		
		if($auto_initialize){
			$this->initialize();
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
	protected function initialize(){
		$this->add('head', $this->data[ 'headelement' ]);
		$this->add('content', $this->renderTemplate('content', array(
			'html' => $this->data['bodytext']
		)));
	}

	/**
	 * This is called by MediaWiki to render the skin.
	 */
	final function execute() {
		// Suppress warnings to prevent notices about missing indexes in $this->data
		wfSuppressWarnings();
		$this->_preExecute();

		$this->data['pageLanguage'] = $this->getSkin()->getTitle()->getPageViewLanguage()->getCode();

		echo $this->renderTemplate('main');

		wfRestoreWarnings();
	}

	/**/
	protected function _preExecute(){

	}

	protected function headElement(){
		return $this->data[ 'headelement' ];
	}

	public function renderTemplate($template, $args=array()){
		ob_start();
		extract($args);
		if($this->options->debug===true){
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
	 * Add html content to a specific hook point
	 * or assign an object method which returns html, it will be run at render time
	 *
	 * eg.  add('before:content', '<h2>Some html content</h2>')
	 * 			add('before:content', array('methodName', $obj))
	 */
	public function add($place, $stringOrClassMethodArray){
		if(!isset($this->hooks[$place]))
			$this->hooks[$place] = array();
		$this->hooks[$place][] = $stringOrClassMethodArray;
	}

	/**
	 * Convenience template method for <?php echo $this->render() ?>
	 */
	function insert($place, $args){
		echo $this->render($place, $args);
	}

	/**
	 * Run content hooks, optionally passing arguments to provide to
	 * object methods
	 */
	protected function render($place, $args=array()){
		$sep = isset($args['seperator']) ? $args['seperator'] : ' ';

		$content = '';
		if(isset($this->hooks[$place])){
			foreach($this->hooks[$place] as $stringOrClassMethod){
				if($this->options['debug']===true){
						$content.='<!--Skinny:Template: '.$template.'-->';
					}
				if(is_array($stringOrClassMethod)){
					$content .= call_user_method_array($stringOrClassMethod[0], $stringOrClassMethod[1], $args);
				}else{
					//treat it like a string
					$content .= $sep . (string) $stringOrClassMethod;
				}
			}
		}
		//content from #movetoskin and #skintemplate
		if( Skinny::hasContent($place) ){
			foreach(Skinny::getContent($place) as $item){

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




} // end of class

