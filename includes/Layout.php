<?php
namespace Skinny;

/**
A layout can define it's own resouces, set options for the main skin,
define additional zones, etc...
*/
abstract class Layout{

  protected static $resourceModules = array();

  protected $templateDir = '/templates';
  protected $templatePath;

  protected $mainTemplate = 'main';

  protected $skin;
  protected $template;

  protected $content = array();

  public static function getResourceModules () {
    return static::$resourceModules;
  }

  function __construct (Skin $skin, Template $template) {
    $this->skin = $skin;
    $this->template = $template;
    $this->templatePath = realpath(dirname(__FILE__).$this->templatePath);

    $template->addTemplatePath($this->templatePath);

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

  public function renderTemplateFile(String $path, Array $args=array()){
    if (!file_exists($path)) {
      return 'Template file '.$path.' does not exist';
    }
    ob_start();
    extract($args);
    require( $path );
    return ob_get_clean();
  }

	/**
	 * Add the result of a function callback to a zone
	 *
	 * 	eg.	add('before:content', array('methodName', $obj))
	 *			add('before:content', 'methodOnThisObject')
	 */
	public function addHook(String $zone, $hook, Array $args=array()){
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
	public function addTemplate(String $zone, String $template, Array $params=array()){
		if(!isset($this->content[$zone]))
			$this->content[$zone] = array();
		$this->content[$zone][] = array('type'=>'template', 'template'=>$template, 'params'=>$params);
	}

	/**
	 * Add html content to a zone
	 *
	 * eg.  add('before:content', '<h2>Some html content</h2>')
	 */
	public function addHTML(String $zone, String $content){
		if(!isset($this->content[$zone]))
			$this->content[$zone] = array();
		$this->content[$zone][] = array('type'=>'html', 'html'=>$content);
	}

	/**
	 * Add a zone to a zone. Allows adding zones without editing template files.
	 *
	 * eg.  add('before:content', 'zone name')
	 */
	public function addZone(String $zone, String $name, Array $params=array()){
		if(!isset($this->content[$zone]))
			$this->content[$zone] = array();
		$this->content[$zone][] = array('type'=>'zone', 'zone'=>$name, 'params'=>$params);
	}

	/**
	 * Convenience template method for <?php echo $this->renderZone() ?>
	 */
	public function insert(String $zone, Array $args=array()){
		echo $this->renderZone($zone, $args);
	}
	public function before(String $zone, Array $args=array()){
		$this->insert('before:'.$zone, $args);
	}
	public function after(String $zone, Array $args=array()){
		$this->insert('after:'.$zone, $args);
	}
	public function prepend(String $zone, Array $args=array()){
		$this->insert('prepend:'.$zone, $args);
	}
	public function append(String $zone, Array $args=array()){
		$this->insert('append:'.$zone, $args);
	}
	public function attach(String $zone, Array $args=array()){
		$this->prepend($zone, $args);
		$this->insert($zone, $args);
		$this->append($zone, $args);
	}

}
