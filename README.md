Disclaimer
---
*AutoMin for Statamic is an adaptation of Jesse Bunch's [AutoMin add-on for ExpressionEngine](https://github.com/bunchjesse/AutoMin). The statamic version wouldn't be possible without Jesse's fantastic work, and it is being published with his approval.*
  
*The main changes from the ExpressionEngine version is that the settings are moved to a yaml file. Also, HTML compression is not possible, logging is disabled and the way tag parameters worked had to be changed.*

*Apart from that, I hope that I've managed to not mess up Jesse's original code too much, and that it still works as intended. I'm no Statamic pr0, so feel free to school me if I've done something awkward. ;)*


Introduction
---
**The current version of AutoMin for Statamic is made for Statamic version 1.4.x. I will have a look at adapting it to 1.5 when it's out of beta.**

AutoMin for Statamic is an Statamic add-on that automates the combination and compression of your source files and currently supports CSS, JavaScript, and LESS compression.

AutoMin is smart enough to know when you've changed your source files and will automatically regenerate it's cache when appropriate.

For support, please file a bug report or feature request at the repository on Github:    
https://github.com/aelvan/AutoMin-Statamic/issues

Please note: I work on AutoMin in my spare time so I may not be able to address your issues right away. This is why AutoMin is free. The code is well organized and documented so feel free to poke around the and submit pull requests for any improvements you make.


Special Thanks
---
Thanks to the minify project for their CSS compressor and the JSMin project for their JavaScript minifiaction class. Also, thanks goes to leafo for the PHP LESS processor. 

 - Minify: http://code.google.com/p/minify/
 - JSMin: http://www.crockford.com/javascript/jsmin.html
 - LESS for PHP: http://leafo.net/lessphp/


Changelog
---
### Version 1.0.0
 - Initial Release


Installation (Statamix 1.4.x)
---
1. Download and extract the contents of the zip. Copy the _add-ons/automin folder to your Statamic add-ons folder, and _config/automin.yaml to your config folder. 
2. Create the AutoMin cache directory somewhere below your document root, preferably in your theme folder. Make sure it is writable by Apache (most of the time this means giving the folder 777 permissions).
3. Edit the automin.yaml file to reflect your current paths.
4. Add the AutoMin template tags to your Statamic templates. 
5. Refresh your site. If all goes well, you'll see your CSS and JS code combined and compressed. Note: the first page load after changing your source files or the AutoMin template tags could take longer than usual while your code is compressed.


Example Usage
---

####JavaScript

    {{ automin type="js" }}
        <script src="/{{ theme_path }}js/jquery,js"></script>
        <script src="/{{ theme_path }}js/jquery.ui.js"></script>
        <script src="/{{ theme_path }}js/jquery.ui.mouse.js"></script>
        <script src="/{{ theme_path }}js/jquery.ui.position.js"></script>
        <script src="/{{ theme_path }}js/jquery.ui.widget.js"></script>
        <script src="/{{ theme_path }}js/jquery.ui.draggable.js"></script>
        <script src="/{{ theme_path }}js/gsap/plugins/CSSPlugin.min.js"></script>
        <script src="/{{ theme_path }}js/gsap/easing/EasePack.min.js"></script>
        <script src="/{{ theme_path }}js/gsap/TweenLite.min.js"></script>
        <script src="/{{ theme_path }}js/main.js"></script>
    {{ /automin }}

####CSS

    {{ automin type="css" attr="rel='stylesheet'" }}
        <link rel="stylesheet" href="/{{ theme_path }}css/normalize.css" />
        <link rel="stylesheet" href="/{{ theme_path }}css/core.css" />
        <link rel="stylesheet" href="/{{ theme_path }}css/main.css" />
    {{ /automin }}


####LESS

    {{ automin type="less" attr="rel='stylesheet' title='default'" }}  
        <link rel="stylesheet" href="/{{ theme_path }}css/elements.less" />
        <link rel="stylesheet" href="/{{ theme_path }}css/normalize.less" />
        <link rel="stylesheet" href="/{{ theme_path }}css/core.less" />
        <link rel="stylesheet" href="/{{ theme_path }}css/main.less" />
    {{ /automin }}


Currently, you may not combine LESS code with normal CSS code. Use two separate AutoMin tags to compile both CSS and LESS.


Tag Parameters
---
You can specify tag attributes using the attr parameter on the automin tag. For example:

This tag:

    {{ automin type="js" attr="type='text/javascript'" }}

Outputs something similar to:

    <script src="/_themes/theme/automin/7dc66e1b2104b40a9992a3652583f509.js?modified=8832678882928" type="text/javascript"></script>

And this tag:

    {{ automin type="css" attr="rel='stylesheet' title='default' media='screen, projection'" }}  

Outputs something similar to:

    <link href="/_themes/theme/automin/55ed34446f3eac6f869f3fe5b375d311.css?modified=8832678882928" type="text/css" title="default" rel="stylesheet" media="screen, projection">


Compiling LESS
---
If you use AutoMin to compile your LESS source files, you DO NOT need to include the less.js parser file. AutoMin will parse your LESS source file and then compress the CSS output before sending it to your browser.


Troubleshooting
---
Make sure your cache directory is set in the module's settings and that the directory is writeable by PHP. In most cases, you'll need to assign that directory writable permissions. Usually this is 777.

If AutoMin breaks your CSS or JS code, make sure that your code contains no syntax errors. In your JS, you need to make sure that you always terminate JS statements with a semi-colon. Try running your source code through the relevant lint program for a validity check.

Make sure that your CSS images are web-root relative. Use URLs like: `url('/css/img/myimage.jpg')` instead of `url('img/myimage.jpg')`


"Save some CPU cycles and precompile it instead!"
---
(I know this will come up)

NO! Precompiling is the mother of all f-ups. Or, not really... But. I don't have the need for a build tool except for compiling LESS, minification and combining of JS and CSS. Using an add-on like this, I don't need it at all. 

Also, this removes all sources for mistakes and/or misunderstandigs regarding where the source code resides, since the same files will be in all environments. We're a tiny shop, and this comes in handy when handing off projects to other developers and/or clients, who doesn't necessarily use git.
         
But of course... Feel free to precompile all you want! ;)         
