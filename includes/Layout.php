<?php
namespace Skinny;

/**
A layout can define it's own resouces, set options for the main skin,
define additional zones, etc...
*/
abstract class Layout{

	protected static $templateDir = '/templates';

	protected $mainTemplateFile = 'main';

  protected $debug = false;


	protected $skin;
	protected $template;

	protected $content = array();

	public static function getResourceModules () {
		return array();
	}

  public static function getHeadModules () {
    return array();
  }

  public static function getTemplateDir () {
    return static::$templateDir;
  }

  public function getMainTemplateFile () {
    return $this->mainTemplateFile;
  }

	function __construct (Skin $skin, Template $template) {
		$this->skin = $skin;
		$this->template = $template;

		// add a template directory relative to the current class file
		//$this->templatePath = realpath($this->getPath().$this->templateDir);

		//$template->addTemplatePath($this->templatePath);

		$this->initialize();
	}

	public function initialize () {

	}

	public function getSkin(){
		return $this->skin;
	}

	public function getTemplate(){
		return $this->template;
	}

	public function getContent(){
		return $this->content;
	}

	public function getMsg ($msg) {
		return $this->getTemplate()->getMsg($msg);
	}

  public function render () {
    // TODO... provide a better access to data than this...
    $this->data = $this->getTemplate()->data;
    return $this->renderTemplate($this->getMainTemplateFile());
  }

  public function renderTemplate($templateFile, Array $args=array()){
    if($this->debug===true){
      echo "\n\n".'<!-- Template File: '.$templateFile.' -->';
    }

    // follow class hierarchy of layouts to build a list of template paths
    $templatePaths = [];
    $parents = $this->getAncestors(get_class($this));
    foreach ($parents as $p) {
      $path = realpath($this->getClassPath($p).$p::getTemplateDir());
      if ($path) {
        $templatePaths[] = $path;
      }
    }

    //try all defined template paths
    $templatePath = false;
    foreach($templatePaths as $path){
      $filepath = $path.'/'.$templateFile.'.tpl.php';
      if( is_file($filepath) ){
        $templatePath = $filepath;
        break; //once we've got a template, stop traversing template_paths
      }
    }
    if($templatePath !== false){
      ob_start();
  		extract($args);
  		require( $templatePath );
  		$html = ob_get_clean();
    }else{
      echo $templatePath;
      $html = 'Template file `'.$templateFile.'.tpl.php` not found!';
    }
    return $html;
  }

  /**
   * Render zone content, optionally passing arguments to provide to
   * object methods
   */
  protected function renderZone($zone, $args=array()){
    $sep = isset($args['seperator']) ? $args['seperator'] : ' ';

		if($this->debug===true){
      echo "\n\n".'<!-- Template Zone: '.$zone.' -->';
    }

    $content = '';
    if(isset($this->content[$zone])){
      foreach($this->content[$zone] as $item){
        switch($item['type']){
          case 'hook':
            //method will be called with two arrays as arguments
            //the first is the args passed to this method (ie. in a template call to $this->insert() )
            //the second are the args passed when the hook was bound
            $content .= call_user_func_array($item['hook'], array($args, $item['arguments']));
            break;
          case 'html':
            $content .= $sep . (string) $item['html'];
            break;
          case 'template':
            $content .= $this->renderTemplate($item['template'], $item['params']);
            break;
          case 'zone':
            $content .= $this->renderZone($item['zone'], $item['params']);
            break;
        }

      }
    }
    //content from #movetoskin and #skintemplate
    if( \Skinny::hasContent($zone) ){
      foreach(\Skinny::getContent($zone) as $item){

        //pre-rendered html from #movetoskin
        if(isset($item['html'])){
          if($this->debug===true){
            $content.='<!--Skinny:MoveToSkin: '.$template.'-->';
          }
          $content .= $sep . $item['html'];
        }
        else
        //a template name to render
        if(isset($item['template'])){
          if($this->debug===true){
            $content.='<!--Skinny:Template (via #skintemplate): '.$item['template'].'-->';
          }
          $content .= $this->renderTemplate( $item['template'], $item['params'] );
        }
      }
    }
    return $content;
  }



	/**
	 * Add the result of a function callback to a zone
	 *
	 * 	eg.	add('before:content', array('methodName', $obj))
	 *			add('before:content', 'methodOnThisObject')
	 */
	public function addHookTo($zone, $hook, Array $args=array()){
		if(!isset($this->content[$zone])){
			$this->content[$zone] = array();
		}
		//allow just a string reference to a method on this skin object
		if (!is_array($hook) && method_exists($this, $hook)) {
			$hook = array($this, $hook);
		}
		if (!is_callable($hook, false, $callbable_name)) {
			throw new \Exception('Invalid skin content hook for zone:'.$zone.' (Hook callback was: '.$callbable_name.')');
		}
		$this->content[$zone][] = array('type'=>'hook', 'hook'=>$hook, 'arguments'=>$args);
	}

	/**
	 * Render the output of a template to a zone
	 *
	 * eg.	add('before:content', 'template-name')
	 */
	public function addTemplateTo($zone, $template, Array $params=array()){
		if(!isset($this->content[$zone]))
			$this->content[$zone] = array();
		$this->content[$zone][] = array('type'=>'template', 'template'=>$template, 'params'=>$params);
	}

	/**
	 * Add html content to a zone
	 *
	 * eg.	add('before:content', '<h2>Some html content</h2>')
	 */
	public function addHTMLTo($zone, $content){
		if(!isset($this->content[$zone]))
			$this->content[$zone] = array();
		$this->content[$zone][] = array('type'=>'html', 'html'=>$content);
	}

	/**
	 * Add a zone to a zone. Allows adding zones without editing template files.
	 *
	 * eg.	add('before:content', 'zone name')
	 */
	public function addZoneTo($zone, $name, Array $params=array()){
		if(!isset($this->content[$zone]))
			$this->content[$zone] = array();
		$this->content[$zone][] = array('type'=>'zone', 'zone'=>$name, 'params'=>$params);
	}

	/**
	 * Convenience template method for <?php echo $this->renderZone() ?>
	 */
	public function insert($zone, Array $args=array()){
		echo $this->renderZone($zone, $args);
	}
	public function before($zone, Array $args=array()){
		$this->insert('before:'.$zone, $args);
	}
	public function after($zone, Array $args=array()){
		$this->insert('after:'.$zone, $args);
	}
	public function prepend($zone, Array $args=array()){
		$this->insert('prepend:'.$zone, $args);
	}
	public function append($zone, Array $args=array()){
		$this->insert('append:'.$zone, $args);
	}
	public function attach($zone, Array $args=array()){
		$this->prepend($zone, $args);
		$this->insert($zone, $args);
		$this->append($zone, $args);
	}

  public function html ($msg) {
    return $this->getTemplate()->html($msg);
  }
  public function text ($msg) {
    return $this->getTemplate()->text($msg);
  }
  public function url ($name) {
    return $this->getTemplate()->data['nav_urls'][$name]['href'];
  }
  public function makeListItem ($key, $item, $options=array()) {
    return $this->getTemplate()->makeListItem($key, $item, $options);
  }

  public function getClassPath ($class) {
    $c = new \ReflectionClass($class);
    return dirname($c->getFileName());
  }

  public function getAncestors ($class=null) {
    if (!$class) {
      $class = get_class($this);
    }
    return \Skinny::getClassAncestors($class);
  }

}
