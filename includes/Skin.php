<?php
namespace Skinny;
class Skin extends \SkinTemplate{

	public $useHeadElement = true;

	/**
	 * A register of valid skin layouts: key=>config
	 */
	protected static $layouts = array();

	/**
	 * An array of modules to be loaded by ResourceLoader
	 */
	protected static $modules = array();

	/**
	 * An array of modules keys which should be loaded when the template is initialized
	 */
	protected static $autoloadModules = array();


	/**
	 * An array of modules to be loaded by ResourceLoader
	 */
	protected static $template_paths = array();


	/**
	 * Boolean to track whether ResourceLoader modules have been added
	 */
	protected static $_modulesRegistered = false;

	protected static $defaultLayout;


	/**
	 * Register resources with the ResourceLoader.
	 *
	 * Handler for Hook: ResourceLoaderRegisterModules hook.
	 */
	public static function ResourceLoaderRegisterModules( \ResourceLoader $rl ){
		self::$_modulesRegistered = true;
		$rl->register( self::$modules );
    return true;
	}

	/**
	 * Can be used to init a skin before the Skin is instantiated
 * eg. to define resources.
	 */
	public static function init(){

	}

	/**
	  * Load required modules with ResourceLoader
	  */
	public function initPage( \OutputPage $out ){
		parent::initPage( $out );

		$out->addModules(self::$autoloadModules);
	}

	/**
 * Loads skin and user CSS files.
 * @param OutputPage $out
 */
function setupSkinUserCss( \OutputPage $out ) {
	parent::setupSkinUserCss( $out );

	//TODO: load modules from parent of layout, too...
	$layoutClass = self::getLayoutClass();
	$layoutTree = \Skinny::getClassAncestors($layoutClass);
	$styles = array('mediawiki.skinning.interface');
	foreach ($layoutTree as $lc) {
		$styles = array_merge($styles, $lc::getHeadModules());
	}

	$out->addModuleStyles( $styles );
}

	/**
	 * Hooking into the template setup process to provide a custom template
	 * and ensure it's initialized with the options it needs.
	 */
	public function setupTemplate( $templateClass, $repository = false, $cache_dir = false ) {
		//allow current layout to specify a different template class
		// $templateClass = isset($this->layout['templateClass']) ? $this->layout['templateClass'] : $templateClass;
		// $options = array();
		// if( isset($this->layout['templateOptions']) ){
		// 	$options += $this->layout['templateOptions'];
		// }
		//instantiate template with the skin options
		$tpl = new $templateClass();

		//ensure that all template paths registered to this skin are added to the template
		//this allows overriding templates without having to create a new template class
		// foreach(self::$template_paths as $path){
		// 	$tpl->addTemplatePath($path);
		// }
		return $tpl;
	}

	/**
	 * Called by OutputPage to provide opportunity to add to body attrs
	 */
	public function addToBodyAttributes( $out, &$attrs){
		$classes = array();
		$layout = $this->getLayout();

		$attrs['class'] .= ' sitename-'.strtolower(str_replace(' ','_',$GLOBALS['wgSitename']));

		$layoutClass = self::getLayoutClass();
		$layoutTree = \Skinny::getClassAncestors($layoutClass);
		$layoutNames = array_flip(self::$layouts);
		foreach ($layoutTree as $lc) {
			if (isset($layoutNames[$lc])) {
				$classes[] = 'layout-'.$layoutNames[$lc];
			}
		}
		if( $GLOBALS['wgUser']->isLoggedIn() ){
			$classes[] = 'user-loggedin';
		}else{
			$classes[] = 'user-anonymous';
		}

		$attrs['class'] .= ' '.implode(' ',$classes);

	}

	/**
	 * Add a new skin layout for this skin
	 */
	public static function addLayout ($name, $className){
		if (!class_exists($className)) {
			throw new \Exception('Invalid Layout class: '.$className);
		}
		static::$layouts[$name] = $className;

		self::addModules($className::getResourceModules());
	}

	public static function setLayout($name) {
		if (!isset(static::$layouts[$name])) {
			throw new \Exception("Layout $name does not exist");
		}
		\Skinny::setLayout($name);
	}

	public function getLayout () {
		return \Skinny::getLayout();
	}

	public function getLayoutClass () {
		if (!isset(static::$layouts[\Skinny::getLayout()])) {
			throw new \Exception("Layout $name does not exist");
		}
		return static::$layouts[\Skinny::getLayout()];
	}

	/**
	 * Set the layout config
	 */
	// public static function setLayoutOptions( $name, $options ){
	// 	if( !isset(self::$layouts[$name]) ){
	// 		return;
	// 	}
	// 	if( isset($options['modules']) ){
	// 		self::addModules( $options['modules'] );
	// 	}
	// 	self::$layouts[$name] = \Skinny::mergeOptionsArrays( self::$layouts[$name], $options );
	// }

	/**
	 * Set the options which will be passed to the layout's TemplateClass
	 */
	// public static function setLayoutTemplateOptions( $name, $options ){
	// 	if(!isset(self::$layouts[$name])){
	// 		return;
	// 	}
	// 	if(!isset(self::$layouts[$name]['templateOptions'])){
	// 		self::$layouts[$name]['templateOptions'] = array();
	// 	}
	// 	self::$layouts[$name]['templateOptions'] = \Skinny::mergeOptionsArrays( self::$layouts[$name]['templateOptions'], $options );
	// }


	/**
	 * Create a new layout which inherits from an existing layout
	 */
	// public static function extendLayout($extend, $name, $config=array()){
	// 	$config['extends'] = $extend;
	// 	self::addLayout($name, $config);
	// }


	public static function addTemplatePath (string $path){
		static::$template_paths[] = $path;
	}


	/**
	 * Build a list of modules to be registered to the ResourceLoader when it initializes.
	 */
	public static function addModules ($modules=array(), $load=false){
		if( static::$_modulesRegistered ){
			throw new Exception('Skin is attempting to add modules after modules have already been registered.');
		}
		if(empty($modules)){
			return;
		}
		static::$modules += (array) $modules;
		if($load){
			static::$autoloadModules += array_keys($modules);
		}
	}

	/**
	 * Add ResourceLoader modules to a specified layout
	 * They will be registered with ResourceLoader and automatically loaded
	 * if the layout is active.
	 */
	public static function addModulesToLayout( $layout, $modules ){
		self::addModules($modules);
	}

	public static function loadModules( $module_names ){
		if (is_string($module_names)) {
			$module_names = array($module_names);
		}
		self::$autoloadModules = array_merge(self::$autoloadModules, $module_names);
	}
}
