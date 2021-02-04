<?php
/*
 * Parser extensions to make working with Skinny even awesomer
 */
class Skinny{

  //static class
  private function __construct(){}

  protected static $extractTOC = true;
  protected static $removeTOC = false;

  public static $content = array();

  protected static $pageSkin = null;
  protected static $skin;
  protected static $skinLayout = null;

  /**
   * Initialization. Hook for BeforeInitialize
   */
  public static function init(&$title, $unused, &$output, &$user, $request, $mediaWiki){
    return true;
  }

  /*
   * Handler for hook: ParserFirstCallInit
   *
   * Register the Skinny parser functions
   */
  public static function ParserFirstCallInit(&$parser){
    $parser->setFunctionHook('movetoskin', 'Skinny::moveToSkinPF');
    $parser->setFunctionHook('setskin', 'Skinny::setSkinPF');
    $parser->setFunctionHook('layout', 'Skinny::setLayoutPF');
    $parser->setFunctionHook('skinsert', 'Skinny::insertTemplatePF');
    $parser->setFunctionHook('imageurl', 'Skinny::getImageURLPF');

    return true;
  }

  /**
   * Handler for hook: OutputPageBeforeHTML
   *
   * While this would ideally live in ParserBeforeTidy, that means our settings
   * aren't loaded when a page is retrieved from the ParserCache, so we do it
   * all here instead...
   *
   * For reasons I'm yet to fathom, this hook is sometimes called before skin init
   * and sometimes after.
   */
  public static function OutputPageBeforeHTML($out, &$html){
    //echo $html; exit;
    $html = self::processMoveContent($html);
    if(self::$extractTOC){
      $html = self::extractTOC($html);
    }
    $html = self::processSetSkin($html);
    $html = self::processSetLayout($html);
    return true;
  }

  public static function OutputPageBodyAttributes($out, $skin, &$attrs) {
    if ($skin instanceof \Skinny\Skin) {
      $classes = array();
      $layout = $skin->getLayout();
  
      $attrs['class'] .= ' sitename-'.strtolower(str_replace(' ','_', $GLOBALS['wgSitename']));
  
      $layoutClass = $skin->getLayoutClass();
      $layoutTree = self::getClassAncestors($layoutClass);
      $layoutNames = array_flip($skin->getLayoutConfig());
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
  }

  //hook for RequestContextCreateSkin
  public static function RequestContextCreateSkin($context, &$skin){

    //there's probably a better way to check for this...
    if(!isset($_GET['useskin'])){
      $key = $GLOBALS['wgDefaultSkin'];
      if( self::$pageSkin ){
        $key = new self::$pageSkin;
      }

      $key = \Skin::normalizeKey( $key );

      $skinNames = \Skin::getSkinNames();
      $skinName = $skinNames[$key];

      $className = "\Skin{$skinName}";
      if (class_exists($className)) {
        $skin = new $className();
        if (isset(self::$skinLayout) && method_exists($skin, 'setLayout')) {
          $skin->setLayout(self::$skinLayout);
        }
      }
    }
    self::$skin = $skin;

    return true;
  }


  public static function build( $path, $options=array() ){
    $options['template_path'] = $path;
    return new Slim( $options );
  }

  //Parser function: {{#movetoskin: target | content}}
  public static function moveToSkinPF ($parser, $name='', $content=''){
    //we have to wrap the inner content within <p> tags, because MW screws up otherwise by placing a <p> tag before and after with related closing and opening tags within
    //php's DOM library doesn't like that and will swap the order of the first closing </p> and the closing </movetoskin> - stranding everything after that outside the <movetoskin> block. Lame.
    //$content = $parser->recursiveTagParse($content);
    $content = '<ins data-type="movetoskin" data-name="'.$name.'">'.$content.'</ins>';
    return array( $content, 'noparse' => false, 'isHTML' => false );
  }

  //Parser function: {{#setskin: skin-name}}
  public static function setSkinPF($parser, $skin){
    $content = '<p>SETSKIN:'.$skin.':NIKSTES</p>';
    return array( $content, 'noparse' => true, 'isHTML' => true );
  }

  //Parser function: {{#skin variant: skin-name}}
  public static function setLayoutPF ($parser, $variant){
    $content = '<p>LAYOUT:'.$variant.':TUOYAL</p>';
    return array( $content, 'noparse' => true, 'isHTML' => true );
  }

  //Parser function: {{#skintemplate:template-name|argument=value|argument=value}}
  //render a template from a skin template file...
  public static function insertTemplatePF ($parser, $template, $spot=''){
    //process additional arguments into a usable array
    $params = array();
    //sanitize the template name
    $template = preg_replace('/[^A-Za-z0-9_\-]/', '_', $template);
    //this will be stripped out, assuming the skin is based on Skinny.template
    return '<p>ADDTEMPLATE('.$spot.'):'.$template.':ETALPMETDDA</p>';
  }

  public static function getImageURLPF ( &$parser, $name = '', $arg = 'abs' ) {
    $img = Image::newFromName( $name );
    if($img!==NULL){
      return (  trim($arg==='abs') ? $GLOBALS['wgServer'] : '') . $img->getURL();
    }
    return '';
  }



  protected static function processMoveContent($html){
   if(empty($html))
     return $html;

    if( preg_match_all('~!!!MOVETOSKIN data-type="movetoskin" data-name="([\w:-]+)"!!!([\S\s]*?)!!!\/MOVETOSKIN!!!~m', $html, $matches, PREG_SET_ORDER) ){
      foreach($matches as $match){
        if( !isset( self::$content[ $match[1] ] )){
          self::$content[ $match[1] ] = array();
        }
        array_push( self::$content[ $match[1] ] , array(
          'html' => $match[2]
        ));
        $html = str_replace($match[0], '', $html);
      }
    }

    return $html;
  }

  public static function setLayout ($layout) {
    self::$skinLayout = $layout;
  }
  public static function getLayout () {
    return self::$skinLayout;
  }


  public static function hasContent($target){
    if(isset(self::$content[$target]))
      return true;
    return false;
  }

  public static function getContent($target=null){
    if($target!==null){
      if( self::hasContent($target) ){
        return self::$content[$target];
      }
    }else{
      return array();
    }
  }

  public static function extractTOC( $html ){
    //a super hacky way to grab the TOC.  If the TOC HTML is changed in future versions this will break.
    //just grabs everything from <div id="toc" to </li></ul></div>
    if( preg_match('~<div id="toc"([\S\s]*?)</li>\n\s*</ul>\n\s*</div>~mi', $html, $match)){

      if(self::$removeTOC){
        $html = str_replace($match[0], '', $html);
      }
      $toc = str_replace('id="toc"', '', $match[0]);
      self::$content['toc'] = array( array('html'=>$toc) );
    }
    return $html;
  }

  //check the html for any set skin tokens
  protected static function processSetSkin($html){
    $pattern = '~<p>SETSKIN:([\w_-]+):NIKSTES<\/p>~m';
    if( preg_match_all($pattern, $html, $matches, PREG_SET_ORDER) ){
      $skin = array_pop($matches);

      //if OutputPageBeforeHTML isn't called before RequestContextCreateSkin
      //then this will have no effect... need a way to hack around the problem...
      self::$pageSkin = $skin[1];

      $html = preg_replace($pattern, '', $html);
    }
    return $html;
  }

  //check the html for any set skin tokens
  protected static function processSetLayout($html){
    $pattern = '~<p>LAYOUT:([\w_-]+):TUOYAL<\/p>~m';
    if( preg_match_all($pattern, $html, $matches, PREG_SET_ORDER) ){
      $layout = array_pop($matches);

      self::setLayout($layout[1]);

      $html = preg_replace($pattern, '', $html);
    }
    return $html;
  }

  public static function getClassAncestors ($class) {
    for ($classes[] = $class; $class = get_parent_class ($class); $classes[] = $class);
    return $classes;
  }


}
