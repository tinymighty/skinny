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

	protected $showBreadcrumbs = false;
	protected $breadcrumbsZone = 'prepend:title';

	protected $template_paths = array();

	public function setLayout ($name) {
		$this->getSkin()->setLayout($name);
	}
	public function getLayout () {
		return $this->getSkin()->getLayout();
	}
	public function getLayoutClass () {
		return $this->getSkin()->getLayoutClass();
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


		if( !$this->getLayoutClass()	){
			throw new \Exception('No layout class defined.');
		}
		$layoutClass = $this->getLayoutClass();
		$layout = new $layoutClass($this->getSkin(), $this);

		//parse content first, to allow for any ADDTEMPLATE items
		$content = $this->parseContent($this->data['bodycontent'], $layout);

		//set up standard content zones
		//the logo image defined in LocalSettings
		$layout->addHTMLTo('logo', $this->data['logopath']);
		$layout->addHTMLTo('prepend:body', $this->html( 'prebodyhtml' ));
		//the article title
		if($this->showTitle){
			$layout->addHTMLTo('content-container.class', 'has-title');
			$layout->addHTMLTo('title-html', $this->data['title']);
		}
		//article content
		$layout->addHTMLTo('content-html', $content);
		//the site notice
		if( !empty($this->data['sitenotice'])){
			$layout->addHTMLTo('site-notice', $this->data['sitenotice']);
		}
		//the site tagline, if there is one
		if($this->showTagline){
			$layout->addHTMLTo('content-container.class', 'has-tagline');
			$layout->addHTMLTo('tagline', $this->getMsg('tagline') );
		}
		// $breadcrumbTrees = $this->breadcrumbs();
		// $layout->addTemplateTo('breadcrumbs', 'breadcrumbs', array('trees' => $breadcrumbTrees) );

		if(\Skinny::hasContent('toc')){
			$layout->addHTMLTo('toc', (\Skinny::getContent('toc')[0]['html']));
		}

		// if ( $this->data['dataAfterContent'] ) {
		// 	$layout->addHTMLTo('append:content', $this->data['dataAfterContent']);
		// }
		//the contents of Mediawiki:Sidebar
		//add the usual mediawiki sidebar content
		$layout->addTemplateTo('mediawiki-sidebar', 'classic-sidebar', array(
			'sections'=>$this->data['sidebar']
		));

		// //list of language variants
		// $layout->addTemplate('language-variants', 'language-variants', array(
		// 	'variants'=>$this->data['language_urls']
		// ));

		//page footer
		$layout->addTemplateTo('footer', 'footer', array(
			'links'=> $this->getFooterLinks("flat"),
			'icons'=> $this->get("footericons")
		));

		//mediawiki needs this to inject script tags after the footer
		$layout->addHookTo('append:body', array($this,'afterFooter'));

		$this->data['pageLanguage'] = $this->getSkin()->getTitle()->getPageViewLanguage()->getCode();

		//allow skins to set up before render
		$this->initialize();

		echo $layout->render();
	}


	/**
	 * Transclude a MediaWiki page
	 */
	function transclude($string){
		echo $GLOBALS['wgParser']->parse('{{'.$string.'}}', $this->getSkin()->getRelevantTitle(), new ParserOptions)->getText();
	}


	//parse the bodytext and insert any templates added by the skintemplate parser function
	public function parseContent( $html, $layoutInstance ){
		$pattern = '~<p>ADDTEMPLATE\(([\w_:-]*)\):([\w_-]+):ETALPMETDDA<\/p>~m';
		if( preg_match_all($pattern, $html, $matches, PREG_SET_ORDER) ){
			foreach($matches as $match){
				//if a zone is specified, attach the template
				if(!empty($match[1])){
					$layoutInstance->addTemplateTo($match[1], $match[2]);
					$html = str_replace($match[0], '', $html);
				}else{
				//otherwise inject the template inline into the wikitext
					$html = str_replace($match[0], $layoutInstance->renderTemplate($match[2]), $html);
				}
			}
		}
		return $html;
	}

	/*
	Convert a MediaWiki:message into a navigation structure
	Builds on Skin::addToSidebar to move all headerless entries into the primary navigation*/
	public function processNavigationFromMessage( $message_name ){
		$nav = array();
		$this->getSkin()->addToSidebar($nav, $message_name);

		return $nav;
	}


	public function afterFooter(){

		// $skin = $this->getSkin();
		// $options = $skin->getOptions();

		// return $options['bodyOnly'] ? '' : WrappedString::join( "\n", [
		// 		MWDebug::getDebugHTML( $skin->getContext() ),
		// 		$this->get( 'bottomscripts' ),
		// 		$this->get( 'reporttime' )
		// ] );
	}

	/* Render the category heirarchy as breadcrumbs */
	protected function breadcrumbs() {

		// get category tree
		$parenttree = $this->getSkin()->getTitle()->getParentCategoryTree();
		return;
		// $linkRenderer = \MediaWikiServices::getInstance()->getLinkRenderer();
		// $trees = array();

		// foreach ( $parenttree as $element => $parent ) {
		// 		if ( empty( $parent ) ) {
		// 				# element start a new list
		// 				$trees[$element] = [];
		// 				foreach ($element as $el2 => $par2) {
		// 					$eltitle = Title::newFromText( $element );
		// 					$trees[$element][] = $par2;
		// 				}
		// 		} else {
		// 			$trees[$element] = [$parent];
		// 		}
		// }
		// 		# add our current element to the list
				
		// 		$rendered .= $linkRenderer->makeLink( $eltitle, $eltitle->getText() );


		// // Skin object passed by reference cause it can not be
		// // accessed under the method subfunction drawCategoryBrowser
		// $temp = explode( "\n", $rendered );
		// unset( $temp[0] );
		// asort( $temp );

		// if (empty($temp)) {
		// 		return array();
		// }
		// $trees = array();
		// foreach ($temp as $line) {
		// 	preg_match_all('~<a[\S\s]+?</a>~', $line, $matches);
		// 	$trees[] = $matches[0];
		// }

		// return $trees;
	}



} // end of class
