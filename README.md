# Skosh - PHP Static Site Generator

Skosh takes Markdown and Textile files and combines them with Twig templates to produce a set of static HTML files, while JavaScript and LESS files are managed by Gulp. Supports RSS and sitemaps.

----------

## Installation

```
$ composer require torann/skosh-generator
```

This will put the Gulp command in your system path, allowing it to be run from any directory.

## Basic Usage

### `$ php skosh init`

Initialize a new site.

### `$ php skosh build`

To generate an existing site, in the site folder.

**Options:**

```
 --env,-e            Which environment to build for. (default: "local")
 --part,-p           Which part of the site to build pages, assets, or 
                     all. (default: "all")
```

### `$ php skosh serve`

To serve a site using Gulp.

**Options:**

```
 --port,-p           Port to listen on. (default: 8000)
 --host,-H           Host to listen on. (default: "localhost")
```

## Publish to a Remote Server

Update the `remote.yml` file to setup you remote server.

```
$ php skosh publish [server]
```
**Options:**

```
 --env,-e            Which environment to publish. (default: "production")
```

### Dependencies:

* [lftp](http://lftp.yar.ru/) is required to publish to a FTP server.
* [rsync](http://rsync.samba.org/) is required to publish to a SSH server.

## Protecting Sensitive Configuration

It is advisable to keep all of your sensitive configuration out of your configuration files. Things such as FTP passwords, API keys, and encryption keys should be kept out of your configuration files whenever possible. So, where should we place them? Thankfully, Skosh provides a very simple solution to protecting these types of configuration items using "dot" files.

The `.remote.yml` file within the root of your project contains the FTP/SSH credentials for the remote production server. To create a remote configuration file for a another server, simple create a "dot" remote file with the remote server's name in it `.remote_staging.yml`. This will be used when publishing to the remove staging server `skosh publish ftp --env=staging`;

## Directory structure

```
skosh-project-root
├─── config.yml
├─── config_prod.yml
├─── gulpfile.js
├─── package.json
├─── .remote.yml
└─── source
     ├─── about.textile
     ├─── index.twig
     ├─── sitemap.xml
     ├─── rss.xml
     ├─── _posts
     │    ├─── 2014-01-01-my-first-post.md
     │    └─── 2014-01-03-my-second-post.textile
     │
     ├─── news
     │    ├───  index.twig
     │    └─── _posts
     │         ├─── 2014-02-15-polar-vortex.md
     │         └─── 2014-01-24-muppets-take-manhattan.md
     │
     ├─── _includes
     │    ├─── footer.twig
     │    └─── topbar.twig
     │
     └─── _templates
          ├─── default.twig
          └─── post.twig
```
