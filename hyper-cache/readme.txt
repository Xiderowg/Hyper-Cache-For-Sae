=== Hyper Cache ===
Tags: cache,performance,staticizer,apache,htaccess,tuning,speed,bandwidth,optimization,tidy,gzip,compression,server load,boost
Requires at least: 2.5
Tested up to: 4.1
Stable tag: trunk
Donate link: http://www.satollo.net/donations
Contributors: satollo

Hyper Cache is a performant and easy to configure cache system for WordPress.

== Description ==

Hyper Cache is a cache plugin specifically written to get the maximum
speed for your WordPress blog. It can be used in low resources hosting as well
on high end servers.

Hyper Cache is purely PHP and works on every blog: no complex configurations are need
and when you deactivate it no stale setting are left floating around.

Short list of features:

* Mobile aware: double cache for desktop and mobile site versions
* Mobile theme switch option: change the theme on mobile device detection
* Able to serve expired pages to bots to increase the perceived speed by bots
* Manages compression even on the fly for non cached pages
* Lots of configurable bypasses: matching cookies, matching urls, user agents, ...
* Comments aware: is able to serve cached pages even to visitors who commented the blog (perfect for
blog with great readers paritipation)
* Cache folder can be moved outside your blog space to exclude it from backups
* Controls over cache cleaning on blog events (post edited, comments, ...)
* Autoclean to controls the cache used disk space
* CDN support (experimental)
* Other special options
* Response header signature to check the working status

More can be read on the [Hyper Cache official page](http://www.satollo.net/plugins/hyper-cache).

Other plugins by Stefano Lissa:

* [Newsletter](http://www.thenewsletterplugin.com)
* [Header and Footer](http://www.satollo.net/plugins/header-footer)
* [Include Me](http://www.satollo.net/plugins/include-me)


== Installation ==

1. Put the plugin folder into [wordpress_dir]/wp-content/plugins/
2. Go into the WordPress admin interface and activate the plugin
3. Optional: go to the options page and configure the plugin

== Frequently Asked Questions ==

See the [Hyper Cache official page](http://www.satollo.net/plugins/hyper-cache) or
the [Hyper Cache official forum](http://www.satollo.net/forums/forum/hyper-cache-plugin).

== Screenshots ==

1. The main configuration panel

2. Configuration of bypasses (things you want/not want to be cached)

3. Mobile devices configuration

== Changelog ==

= 3.1.9 =

* Fixed translations
* Reviews CDN options (now available to all)

= 3.1.8 =

* Fixed the comment awaiting notification cached

= 3.1.7 =

* Added experimental support for CDN
* Added on-the-fly compression
* Fixed some headers

= 3.1.6 =

* Fixed the post trashing detection

= 3.1.5 =

* Tidy option removed

= 3.1.4 =

* Fixed an error log always active

= 3.1.3 =

* Fixed the agents bypass
* Added the "serve expired pages to bots" options
* Added the readfile/file_get_contents switch
* Fixed the draft saving triggering a cache invalidation
* Added distinct cache clean for home and archives
* Added debug logging when HYPER_CACHE_LOG is true (define it on wp-config.php)
* Fixed the + sign on comment author

= 3.1.2 =

* Fixed comment author cookie clean

= 3.1.1 =

* fixed a PHP warning on options panel when clearing an empty cache
* pot file added
* possible fix for after update messages that saving is needed

= 3.1.0 =

* Fixed the cookie bypass
* Removed a debug notice
* Added HTTPS separated cache
* Improved code performance

= 3.0.6 =

* readme.txt fix
* WP 4.0 compatibility check
* Fixed invalidation on draft saving

= 3.0.5 =

* Fixed analysis of URL with commas and dots
* Improved the categories invalidation with /%category% permalink

= 3.0.4 =

* Help texts fixed

= 3.0.3 =

* Fixed the autoclean when max cached page age is set to 0
* Changed a little the mobile agent list

= 3.0.2 =

* Added the browser caching option
* Fixed a cache header
* Fixed warning on cache size if empty

= 3.0.1 =

* Short description fix on plugin.php
* Forum link fix on readme.txt
* More help on comment authors option

= 3.0.0 =

* Totally rewritten to include the Lite Cache features

= To Do =

* Register an action to clean the cache by other plugin
* Separated cache for https
* Invalidation of categories paths when /%category%/%postname% is used

