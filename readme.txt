=== Archives for Custom Post Types ===
Contributors: jacklenox
Tags: custom post types, archives
Requires at least: 3.1
Tested up to: 3.9.1
Stable tag: 1.0.2
License: GPL2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A plugin that provides native-like support for dated archive pages of custom post types (e.g. http://yoursite.com/2014/{custom-post-type}/)

== Description ==

This plugin provides proper support for archived pages of custom post types to match the support for normal posts.

The problem is that the function, `wp_get_archives()` does not allow you to pass in a post type. It only works for normal posts. There is a filter called `getarchives_where()` but this doesn't handle the archive URLs (e.g. http://yoursite.com/2014/).

So while the filter will help you view the correct archives, it won't work properly in conjunction with the `wp_get_archives()` function. For example, a link to "June 2014" emitted by wp_get_archives() will only take you to normal posts from June 2014, not custom post types.

This plugin provides a new function: `wp_get_archives_cpt()`. This function can take a post_type argument as well as the usual arguments that can be passed to `wp_get_archives()`. The plugin also provides automatic handling for custom post type archive URLs.

This plugin has stemmed from a ticket that I have been working on in core: https://core.trac.wordpress.org/ticket/21596

Unfortunately a proper patch for this in core will probably have to go quite deep. I have therefore decided to share this plugin as a temporary solution.

== Installation ==

1. Upload `archives-for-custom-post-types.php` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Place `<?php wp_get_archives_cpt( 'post_type=custom_post_type' ); ?>` in your templates

== Frequently Asked Questions ==

I haven't had any yet...

== Screenshots ==

There aren't really any that suit this plugin. Sadly it isn't pretty...

== Changelog ==

= 1.0.2 =
* Fixed pagination links

= 1.0.1 =
* Broke then fixed some stupid minor issues. I've updated trunk and 1.0 a few times so probably better to tag this now.

= 1.0 =
* Initial commit. All seems to be working correctly to me.