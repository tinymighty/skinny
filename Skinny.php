<?php
/**
 * Skinny
 * Simple tools to help advanced skinning techniques.
 * to predefined areas in your skin.
 * Intended for MediaWiki Skin designers.
 * By Andru Vallance - andru@tinymighty.com
 *
 * License: GPL - http://www.gnu.org/copyleft/gpl.html
 *
 */
$wgAutoloadClasses['Skinny'] = dirname(__FILE__) . '/Skinny.class.php';
$wgAutoloadClasses['SkinSkinny'] = dirname(__FILE__) . '/Skinny.skin.php';
$wgAutoloadClasses['SkinnyTemplate'] = dirname(__FILE__) . '/Skinny.template.php';
$wgAutoloadClasses['SkinnySlim'] = dirname(__FILE__) . '/Skinny.slim.php';


$wgExtensionMessagesFiles['SkinnyMagic'] = dirname( __FILE__ ) . '/Skinny.i18n.magic.php';
$wgExtensionMessagesFiles['Skinny'] = dirname( __FILE__ ) . '/Skinny.i18n.php';

$wgHooks['ParserFirstCallInit'][] = 'Skinny::ParserFirstCallInit';
$wgHooks['OutputPageBeforeHTML'][] = 'Skinny::OutputPageBeforeHTML';

$wgHooks['RequestContextCreateSkin'][] = 'Skinny::getSkin';

$wgHooks['ResourceLoaderRegisterModules'][] = 'SkinSkinny::registerModules';


$wgExtensionCredits['parserhook'][] = array(
   'path' => __FILE__,
   'name' => 'Skinny',
   'description' => 'Handy tools for advanced skinning. Move content from the article to the skin, and set skin on a page by page basis.',
   'version' => '0.2', 
   'author' => 'Andru Vallance',
   'url' => 'https://www.mediawiki.org/wiki/Extension:Skinny'
);

Skinny::setOptions();