<?php
/*
 * Parser extensions to make working with Skinny even awesomer
 */
class Skinny{

  //singleton
  private function __construct(){}
  
  public static $content = array();
  protected static $pageSkin = null;
  
  public static function init(&$parser){
    $parser->setFunctionHook('movetoskin', 'Skinny::moveToSkin');
    $parser->setFunctionHook('setskin', 'Skinny::setSkin');
    $parser->setFunctionHook('skinsert', 'Skinny::insertTemplate');
    return true;
  }

  //Parser function: {{#movetoskin: target | content}}
  public static function moveToSkin($parser, $name='', $content=''){
    //we have to wrap the inner content within <p> tags, because MW screws up otherwise by placing a <p> tag before and after with related closing and opening tags within
    //php's DOM library doesn't like that and will swap the order of the first closing </p> and the closing </movetoskin> - stranding everything after that outside the <movetoskin> block. Lame.
    //$content = $parser->recursiveTagParse($content);
    $content = '<ins data-type="movetoskin" data-name="'.$name.'">'.$content.'</ins>';
    return array( $content, 'noparse' => false, 'isHTML' => false );
  }

  //Parser function: {{#setskin: skin-name}}
  public static function setSkin($parser, $skin){
    $content = 'SETSKIN:'.$skin.':NIKSTES';
    return array( $content, 'noparse' => true, 'isHTML' => true );
  }

  //Parser function: {{#skintemplate:template-name|argument=value|argument=value}}
  //render a template from a skin template file...
  public static function insertTemplate($parser, $template, $spot=false){
    //process additional arguments into a usable array

    /*
    //Using these arguments means passing them through extract()
    //Until we can find a secure way to sanitize the input, it's a no-go
    $args = func_get_args();
    $args = array_slice($args, 2, count($args) );
    $params = array();
    foreach($args as $a){
      if(strpos($a, '=')){
        $exploded = explode('=', $a);
        $params[trim($exploded[0])] = trim($exploded[1]);
      }
    }*/
    $params = array();

    //sanitize the template name
    $template = preg_replace('/[^A-Za-z0-9_\-]/', '_', $template);

    if($spot){
      if(!isset(self::$content[$spot])){
        self::$content[$spot] = array();
      }
      self::$content[$spot][] = array('template'=>$template, 'params'=>$params);
      return '';
    }else{
      //this will be stripped out, assuming the skin is based on Skinny.template
      return 'ADDTEMPLATE:'.$template.':ETALPMETDDA';
    }
  }
  
  public static function run($out, &$html){
    $html = self::processMoveContent($html);
    $html = self::processSetSkin($html);
    return true;
  }

  protected static function processMoveContent($html){
   if(empty($html))
     return $html;

    if( preg_match_all('~<ins data-type="movetoskin" data-name="(\w+)">([\S\s]*?)<\/ins>~m', $html, $matches, PREG_SET_ORDER) ){
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
    
  public static function hasContent($target){
    if(isset(self::$content[$target]))
      return true;
    return false;
  }
  
  public static function getContent($target=null){
    if($target!==null){
      if( self::hasContent($target) )
        return self::$content[$target];
    }else{
      return array();
    }
  }



  //check the html for any set skin tokens
  protected static function processSetSkin($html){
    $pattern = '~SETSKIN:([\w_-]+):NIKSTES~m';
    if( preg_match_all($pattern, $html, $matches, PREG_SET_ORDER) ){
      $skin = array_pop($matches);
      self::$pageSkin = $skin[1];
      $html = preg_replace($pattern, '', $html);
    }
    return $html;
  }

  //hook for RequestContextCreateSkin
  public static function getSkin($context, &$skin){
    if(self::$pageSkin){
      $skin = self::$pageSkin;
    }
    return true;
  }


}