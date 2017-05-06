<?php
/**
 * Pockethold - Flarum Web Installer.
 * Downloads composer, runs create-project flarum/flarum and remove itself.
 * Author: Andre Herberth
 * License: MIT
 * DISCLAIMER: THIS IS DIRTY. USE WITH CARE. SUGGESTIONS ARE WANTED. It was written as a
 */
use Composer\Console\Application;
use Composer\Command\CreateProjectCommand;
use Composer\IO\IOInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

//Set error reporting
error_reporting(E_ALL);

//Attempt at fixing Github - Need a new one, as this one is the same Lucious used in his concept.
const GITHUB_TOKEN = 'ec785da935d5535e151f7b3386190265f00e8fe2';

//Increase Memory Limit. First attempt at setting 1G. If this fails, check if memory is 512 or more. Die if not.
$ini_get_option_details = ini_get_all ();
if ($ini_get_option_details['memory_limit']['access'] & INI_USER)
{
    ini_set('memory_limit', '1G');
    //  phlog('Memory: Attempte to set 1GB Memory Limit. ');
    //  phlog('Memory: Set to' . ini_get['memory_limit'] );
}
if($ini_get_option_details['memory_limit'] >= '512M')
{
    // phlog('Memory: ' . ini_get['memory_limit'] );
}
else
{
    //  phlog('Memory: Not enough memory. Memory set at: ' .ini_get['memory_limit'] );
    die();
}




//Then check if this was allowed.
if ( !defined('ABSPATH') )
{
    define('ABSPATH', dirname(__FILE__) . '/');
}
$tmppath = (ABSPATH . 'temp/');
if ( !file_exists($tmppath) )
{
    mkdir($tmppath);
}


/**
 * phlog write log requests to temp/install.log
 */
function phlog(){

}

/**
 * Removes installer, temp and redirects to ./ to start forum install.
 *
 * @param $temp -
 */
function removeinstaller($temp)
{
    rrmdir($temp);
    unlink(__FILE__);

}

/**
 * getfile($src, $dest) - downloads a file and saves it on the web server.
 *
 * @param $src
 * @param $dest
 * @return bool
 */
function getfile($src, $dest)
{

    if(!file_put_contents($dest, fopen($src, 'r')))
    {
        //Shamelessly stolen, and herby credited, from Luceos's flarum installer proof of concept.
        $c = curl_init($src);
        curl_setopt_array($c, [
            CURLOPT_RETURNTRANSFER => true
        ]);
        $phar = curl_exec($c);
        curl_close($c);
        file_put_contents($dest, $phar);
        unset($phar);
    }


}

function poststatus($temp, $path)
{
    $i = "prepare";

    if(file_exists($temp . 'vendor/autoload.php'))
    {
        $i = "composer";
    }
    if(file_exists($path .'flarum/index.php') && !file_exists($temp . 'compose.done'))
    {
        $i = "waiting";
    }

    if(file_exists($temp . 'compose.done'))
    {
        $i = "cleanup";
    }

    echo $i;
}

/**
 * Recursively delete files
 * Credits: http://stackoverflow.com/questions/3338123/how-do-i-recursively-delete-a-directory-and-its-entire-contents-files-sub-dir
 * @param String $dir - Source of files being moved
 */
function rrmdir($dir)
{
    if ( is_dir($dir) ) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ( $object != "." && $object != ".." ) {
                if ( is_dir($dir . "/" . $object) )
                    rrmdir($dir . "/" . $object);
                else
                    unlink($dir . "/" . $object);
            }
        }
        rmdir($dir);
    }
}

/**
 * Recursively move files from one directory to another
 *
 * @param String $src - Source of files being moved
 * @param String $dest - Destination of files being moved
 * @return NULL
 */
function rmove($src, $dest)
{

    // If source is not a directory stop processing
    if ( !is_dir($src) ) return false;

    // If the destination directory does not exist create it
    if ( !is_dir($dest) ) {
        if ( !mkdir($dest) ) {
            // If the destination directory could not be created stop processing
            return false;
        }
    }

    // Open the source directory to read in files
    $i = new DirectoryIterator($src);
    foreach ($i as $f) {
        if ( $f->isFile() ) {
            rename($f->getRealPath(), "$dest/" . $f->getFilename());
        } else if ( !$f->isDot() && $f->isDir() ) {
            rmove($f->getRealPath(), "$dest/$f");
            unlink($f->getRealPath());
        }
    }
    unlink($src);
}

if(isset($_REQUEST["ajax"]) && !empty($_REQUEST["ajax"])){

    if($_REQUEST["ajax"] == 'status')
    {
        poststatus($tmppath, ABSPATH);
    }
    elseif($_REQUEST["ajax"] == 'prepare')
    {

        if (!file_exists($tmppath . 'composer.phar')) {
            getfile("https://getcomposer.org/composer.phar", $tmppath . 'composer.phar');
        }
        $composer = new Phar($tmppath . "composer.phar");
        $composer->extractTo($tmppath);
        echo "Prepare: Completed";
    }
    elseif($_REQUEST["ajax"] == 'composer')
    {
        ignore_user_abort(true);
        set_time_limit(500);
        require_once($tmppath . 'vendor/autoload.php');
        putenv('COMPOSER_HOME=' . $tmppath);
        putenv('COMPOSER_NO_INTERACTION=true');
        putenv('COMPOSER_PROCESS_TIMEOUT=300');
        $application = new Application();
        $application->setAutoExit(false);
        $input = new ArrayInput([
            'command'                 => 'config',
            'github-oauth.github.com' => GITHUB_TOKEN
        ]);
        $application->run($input);
        $application->setAutoExit(false);
        $input = new StringInput('create-project flarum/flarum ./flarum --stability=beta --no-dev --ignore-platform-reqs');
        $application->run($input);
        unset($input);
        unset($application);
        touch($tmppath . 'compose.done');

    }
    elseif($_REQUEST["ajax"] == 'cleanup')
    {
        rmove(ABSPATH . 'flarum', ABSPATH);
        removeinstaller($tmppath);
        echo "Complete";
    }
    else
    {
        die("Access denied!");
    }


} else {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <!-- Latest compiled and minified CSS -->
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
        <script
                src="https://code.jquery.com/jquery-3.2.1.min.js"
                integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4="
                crossorigin="anonymous"></script>
    </head>
    <body>
    <div class="container">
        <div class="jumbotron" style="background-color: transparent;">
            <div class="container text-center">

                <img style="margin: auto;" class="img-responsive" alt="Pockethold" src="data:image/png;base64,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" />
                <p style="max-width: 460px; margin:auto;">Pockethold is a 3rd party Flarum downloader.</p>
                <p style="max-width: 460px; margin:auto;">The sole purpose is to provide a way to install Flarum without shell.</p>
                <p style="max-width: 460px; margin:50px auto auto auto;"><span id="btnstart">Checking Status</span></p>
            </div>
        </div>
    </div>

    <script>
        //Javascript refactor pass 1. - Cleanup, comments and reusable code.

        /*
         *
         *
         * */

        //First - Lets set up some variables
        var timer;
        var count = 0;
        var preparebrn = '<span id="preparebtn" class="instal1 btn btn-primary btn-lg" role="button">Step 1: Prepare</span>';
        var cleanupbtn = '<span id="cleanupbtn" class="btn btn-primary btn-lg" role="button">Step 3: Finish</span><span id="btnbazaar" class="btn btn-lg" role="button">Install Bazaar</span>';
        var composerbtn = '<span id="composerbtn" class="instal1 btn btn-primary btn-lg" role="button">Step 2: Install</span>';

        // Functions

        // phajax function creates a reusable wrapper for common ajax calls. Shorting down codebase.
        function phajax(phtype, phdata, pherrm, phsuccess, phclick, phfailfunc, phsuccessfunc) {
            $(document).on ("click", phclick, function () {
                return $.ajax({
                    url: window.location.href,
                    data: {ajax: phdata},
                    type: phtype
                })
                    .done(function(res) {
                        console.log(res);
                        $( ".instal1" ).replaceWith(phsuccess);
                        if (phsuccessfunc) {
                            eval(phsuccessfunc);
                        }
                    })
                    .fail(function(err) {
                        console.log('Error: ' + err.status);
                        $( ".install" ).replaceWith(pherrm);
                        if (phfailfunc) {
                            eval(phfailfunc);
                        }
                    })
            });
        };

        //Status checker used during the composer install
        function poll(url) {
            timer = setTimeout(function() {
                $.ajax({
                    url: url,
                    data: {ajax: "status"},
                    type: 'get'
                })
                    .done(function(data) {
                        if (data === 'cleanup') {
                            $(".instal1").replaceWith('<span id="btncleanup" class="btn btn-primary btn-lg" role="button">Step 3: Finish</span><span id="btnbazaar" class="btn btn-lg" role="button">Install Bazaar</span>');
                        }
                        else {
                            if (++count > 50) {
                                $(".instal1").replaceWith('<h2 class="instal1">Install failed :-(</h2>');
                            }

                            else {
                                $(".instal1").replaceWith('<h2 class="instal1">Still Downloading!</h2>');
                                poll(url);
                            }
                        }

                    })
            },5000)
        };

        // Runs at startup.
        $( document ).ready(function() {
            $.ajax({
                url: window.location.href,
                data: {ajax: "status"},
                type: 'post'
            })
                .done(function(res) {
                    console.log(res);
                    if (res === 'prepare') {
                        $( "#btnstart" ).replaceWith( '<span id="preparebtn" class="instal1 btn btn-primary btn-lg" role="button">Step 1: Prepare</span>' );
                    } else if (res === 'composer') {
                        $( "#btnstart" ).replaceWith( '<span id="composerbtn" class="instal1 btn btn-primary btn-lg" role="button">Step 2: Install</span>' );
                    } else if (res === 'cleanup') {
                        $("#btnstart").replaceWith('<span id="cleanupbtn" class="btn btn-primary btn-lg" role="button">Step 3: Finish</span><span id="btnbazaar" class="btn btn-lg" role="button">Install Bazaar</span>');
                    } else if (res === 'waiting') {
                        $("#btnstart").replaceWith('<h2 class="instal1">Still Downloading!</h2>');
                        poll(window.location.href);
                    }
                })
                .fail(function(err) {

                    console.log('Error: ' + err.status);
                    $( ".install" ).replaceWith( '<h2 class="instal1">Error:' + err.status + '</h2>' );
                });
        });

        //What happens on click Prepare
        $(document).ready(function (){
            phajax('post', 'prepare', 'Something went wrong', composerbtn, '#preparebtn', '', '');
        });

        //On Click Composer
        $(document).ready ( function () { $(document).on ("click", "#composerbtn", function () {
            $( "#composerbtn" ).replaceWith( '<h2 class="instal1">Downloading. Please wait.</h2>' );
            poll(window.location.href);
            return $.post( window.location.href, { ajax: "composer"} );
        })
        });
        //On Cleanup
        $(document).ready(function (){
            phajax('post', 'cleanup', 'Something went wrong', '<h2>Redirecting shortly</h2>', '#cleanupbtn', '', 'window.setTimeout(window.location.href = "./",5000);');
        });
    </script>
    </body>
    </html>
    <?php
}
