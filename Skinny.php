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
$wgAutoloadClasses['Skinny'] = __DIR__ . '/Skinny.class.php';
$wgAutoloadClasses['SkinSkinny'] = __DIR__ . '/Skinny.skin.php';
$wgAutoloadClasses['SkinnyTemplate'] = __DIR__ . '/Skinny.template.php';
$wgAutoloadClasses['SkinnySlim'] = __DIR__ . '/Skinny.slim.php';


$wgExtensionMessagesFiles['SkinnyMagic'] = __DIR__ . '/Skinny.i18n.magic.php';
$wgExtensionMessagesFiles['Skinny'] = __DIR__ . '/Skinny.i18n.php';

$wgHooks['BeforeInitialize'][] = 'Skinny::init';
$wgHooks['ParserFirstCallInit'][] = 'Skinny::ParserFirstCallInit';
$wgHooks['OutputPageBeforeHTML'][] = 'Skinny::OutputPageBeforeHTML';

$wgHooks['RequestContextCreateSkin'][] = 'Skinny::getSkin';

$wgHooks['ResourceLoaderRegisterModules'][] = 'SkinSkinny::ResourceLoaderRegisterModules';


$wgExtensionCredits['parserhook'][] = array(
   'path' => __FILE__,
   'name' => 'Skinny',
   'description' => 'Handy tools for advanced skinning. Move content from the article to the skin, and set skin on a page by page basis.',
   'version' => '0.2', 
   'author' => 'Andru Vallance',
   'url' => 'https://www.mediawiki.org/wiki/Extension:Skinny'
);


