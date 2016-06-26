<?php
namespace Skinny;
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
abstract class Template extends \BaseTemplate {

	protected $debug = false;

	protected $showTitle = true;
	protected $showTagline = true;

	protected $showBreadcrumbs = true;
	protected $breadcrumbsZone = 'prepend:title';

	protected $options = array();

	protected $template_paths = array();

	protected $layout;

	public function setLayout ($name, $class) {
		$this->layoutName = $name;
		$this->layoutClass = $class;
	}

	public function getLayout () {
		return $this->layoutName;
	}

	//recursively merge arrays, but if there are key conflicts,
	//overwrite from right to left
	public function mergeOptionsArrays($left, $right){
		return \Skinny::mergeOptionsArrays($left, $right);
	}

	public function setOptions($options, $reset=false){
		if( $reset || empty($this->options) ){
			$this->options = $options;
		}
		$this->options = $this->mergeOptionsArrays($this->options, $options);
	}

	public function addTemplatePath($path){
		if(file_exists($path) && is_dir($path)){
			array_unshift( $this->template_paths, $path);
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
		//parse content first, to allow for any ADDTEMPLATE items
		$content = $this->parseContent($this->data['bodytext']);

		if( !isset($this->layoutClass)	){
			throw new \Exception('No layout class defined.');
		}

		$layout = new ${$this->layoutClass}($this->getSkin(), $this);
		//set up standard content zones
		//head element (including opening body tag)
		$layout->addHTML('head', $this->data[ 'headelement' ]);
		//the logo image defined in LocalSettings
		$layout->addHTML('logo', $this->data['logopath']);
		//the article title
		if($this->options['show title']){
			$layout->addHTML('content-container.class', 'has-title');
			$layout->addTemplate('title', 'title', array(
				'title'=>$this->data['title']
			));
		}
		//article content
		$layout->addHTML('content', $content);
		//the site notice
		if( !empty($this->data['sitenotice'])){
			$layout->addTemplate('notice', 'site-notice', array(
				'notice'=>$this->data['sitenotice']
			));
		}
		//the site tagline, if there is one
		if($this->options['show tagline']){
			$layout->addHTML('content-container.class', 'has-tagline');
			$layout->addTemplate('tagline', 'tagline', array(
				'tagline'=>wfMsg('tagline')
			));
		}
		$layout->addHook('breadcrumbs', 'breadcrumbs');

		//the contents of Mediawiki:Sidebar
		$layout->addTemplate('classic-sidebar', 'classic-sidebar', array(
			'sections'=>$this->data['sidebar']
		));
		//list of language variants
		$layout->addTemplate('language variants', 'language-variants', array(
			'variants'=>$this->data['language_urls']
		));

		//page footer
		$layout->addTemplate('footer', 'footer', array(
			'icons'=>$this->getFooterIcons( "icononly" ),
			'links'=>$this->getFooterLinks( "flat" )
		));
		//mediawiki needs this to inject script tags after the footer
		$layout->addHook('after:footer', 'afterFooter');


		$this->data['pageLanguage'] = $this->getSkin()->getTitle()->getPageViewLanguage()->getCode();

		//allow skins to set up before render
		$this->initialize();

		echo $this->render();
	}

	protected function render () {
		return $this->renderTemplate($this->getLayout()->getMainTemplateFile());
	}


	public function renderTemplate(String $template, Array $args=array()){
		if($this->options['debug']===true){
			echo '<div class="skinny-debug">\Skinny\Template: '.$template.'</div>';
		}
		//try all defined template paths
		$path = false;
		foreach($this->template_paths as $path){
			$filepath = $path.'/'.$template.'.tpl.php';
			if( file_exists($filepath) ){
				$path = $filepath;
				break; //once we've got a template, stop traversing template_paths
			}
		}
		if($path !== false){
			$html = $this->getLayout()->renderTemplateFile($path, $args);
		}else{
			$html = 'Template file `'.$template.'.tpl.php` not found!';
		}
		return $html;
	}

	/**
	 * Transclude a MediaWiki page
	 */
	function transclude($string){
		echo $GLOBALS['wgParser']->parse('{{'.$string.'}}', $this->getSkin()->getRelevantTitle(), new ParserOptions)->getText();
	}

	/**
	 * Render zone content, optionally passing arguments to provide to
	 * object methods
	 */
	protected function renderZone(String $zone, $args=array()){
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
						$content .= call_user_func_array(array($item['hook'][1], $item['hook'][0]), array($args, $item['arguments']));
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


  //parse the bodytext and insert any templates added by the skintemplate parser function
  public function parseContent( $html ){
    $pattern = '~<p>ADDTEMPLATE\(([\w_:-]*)\):([\w_-]+):ETALPMETDDA<\/p>~m';
    if( preg_match_all($pattern, $html, $matches, PREG_SET_ORDER) ){
      foreach($matches as $match){
      	//if a zone is specified, attach the template
      	if(!empty($match[1])){
      		$this->addTemplate($match[1], $match[2]);
      		$html = str_replace($match[0], '', $html);
        }else{
        //otherwise inject the template inline into the wikitext
        	$html = str_replace($match[0], $this->renderTemplate($match[2]), $html);
      	}
      }
    }
    return $html;
  }

  /*
  Convert a MediaWiki:message into a navigation structure
  Builds on Skin::addToSidebar to move all headerless entries into the primary navigation*/
  protected function processNavigationFromMessage( $message_name ){
  	$nav = array();
  	$this->getSkin()->addToSidebar($nav, $message_name);

  	return $nav;
  }


  protected function afterFooter(){
		ob_start();
		$this->printTrail();
		return ob_get_clean();
	}

	/* Render the category heirarchy as breadcrumbs */
	protected function breadcrumbs() {

    // get category tree
    $parenttree = $this->getSkin()->getTitle()->getParentCategoryTree();
    $rendered = $this->getSkin()->drawCategoryBrowser( $parenttree );
    /*echo '<pre>';
    print_r($parenttree);
    print_r($rendered);
    echo '</pre>';*/
    //exit;
    // Skin object passed by reference cause it can not be
    // accessed under the method subfunction drawCategoryBrowser
    $temp = explode( "\n", $rendered );
    unset( $temp[0] );
    asort( $temp );

    if (empty($temp)) {
        return '';
    }
    $trees = array();
    foreach ($temp as $line) {
    	preg_match_all('~<a[\S\s]+?</a>~', $line, $matches);
    	$trees[] = $matches[0];
    }

    return $this->renderTemplate('breadcrumbs', array('trees' => $trees) );
  }



} // end of class
