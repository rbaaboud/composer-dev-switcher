#!/usr/local/bin/php
<?php

/**
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * composer-dev-switcher
 *
 * PHP CLI script to easily switch some vendor to local @dev version
 *
 * @license http://www.opensource.org/licenses/mit-license.html  MIT License
 * @author rbaaboud <ramzi@baaboud.fr>
 */

// ====================================================================================
// utils

/**
 * Print message
 *
 * @param string $message
 * @param string $type Message class. Can be 'error', 'success' or null
 */
function printMessage($message, $type = null)
{
    if ($type === 'error') {
        echo "\033[31m $message \033[0m" . PHP_EOL;
    } else if ($type === 'success') {
        echo "\033[32m $message \033[0m" . PHP_EOL;
    } else {
        echo ' ' . $message . PHP_EOL;
    }
}

/**
 * Print help messages
 */
function printHelp()
{
    printMessage('Usage: php composer-dev-switcher.php relatovePath');
    printMessage('       php composer-dev-switcher.php ../relative/path/to/repository');
}

/**
 * Print error message and exit
 *
 * @param string $message
 */
function printError($message)
{
    // print error message
    printMessage($message, 'error');

    printHelp();

    exit(1);
}

/**
 * Get Composer json file path
 *
 * @return string
 */
function composerGetComposerJsonFilePath()
{
    return '.' . DIRECTORY_SEPARATOR . 'composer.json';
}

/**
 * Returns array representation of composer.json
 *
 * @param string $composerJsonFilePath
 * @return array
 */
function composerGetComposerJsonAsArray($composerJsonFilePath)
{
    $asArray = json_decode(file_get_contents($composerJsonFilePath), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        printError('composer.json file semms to be invalid.');
    }

    return $asArray;
}

/**
 * Get vendor name by relative path
 *
 * @param string $relativePath
 * @return string
 */
function composerGetVendorNameByRelativePath($relativePath)
{
    printMessage('Get repository vendorName by relativePath ' . var_export($relativePath, true) . '...', 'success');

    $relativePath = preg_replace('/\\' . DIRECTORY_SEPARATOR . '+/', DIRECTORY_SEPARATOR, $relativePath . DIRECTORY_SEPARATOR);
    $relativeRepositoryComposerJsonFilePath = $relativePath . 'composer.json';

    // relativePath is dir?
    if (!is_dir($relativePath)) {
        printError('    relativePath ' . var_export($relativePath, true) . ' is not a directory.');
    }

    // relativePath dir has composer.json file?
    if (!file_exists($relativeRepositoryComposerJsonFilePath)) {
        printError('    no composer.json file found in relativePath ' . var_export($relativePath, true) . '.');
    }

    $relativeRepositoryComposerJsonFileAsArray = composerGetComposerJsonAsArray($relativeRepositoryComposerJsonFilePath);

    if (!array_key_exists('name', $relativeRepositoryComposerJsonFileAsArray)) {
        printError('    ' . var_export('name', true) . ' entry not found in composer.json file.');
    }

    printMessage('    vendorName found ' . var_export($relativeRepositoryComposerJsonFileAsArray['name'], true));

    return $relativeRepositoryComposerJsonFileAsArray['name'];
}

/**
 * Update vendorName version as '@dev'
 *
 * @param array $composerJsonFileAsArray
 * @param string $vendorName
 * @return array
 */
function composerUpdateVendorNameAsDev($composerJsonFileAsArray, $vendorName)
{
    printMessage('Updating vendorName ' . var_export($vendorName, true) . ' to ' . var_export('@dev', true) . ' version...', 'success');

    if (array_key_exists('require', $composerJsonFileAsArray)) {
        if (array_key_exists($vendorName, $composerJsonFileAsArray['require'])) {
            $composerJsonFileAsArray['require'][$vendorName] = '@dev';

            printMessage('    vendorName found in ' . var_export('require', true) . ' entry. Updating...');

            return $composerJsonFileAsArray;
        }
    }

    // vendorNAme not found in 'require'...
    if (array_key_exists('require-dev', $composerJsonFileAsArray)) {
        if (array_key_exists($vendorName, $composerJsonFileAsArray['require-dev'])) {
            $composerJsonFileAsArray['require-dev'][$vendorName] = '@dev';

            printMessage('    vendorName found in ' . var_export('require-dev', true) . ' entry. Updating...');

            return $composerJsonFileAsArray;
        }
    }

    // vendorNAme not found in 'require' and 'require-dev'...
    if (!array_key_exists('require', $composerJsonFileAsArray)) {
        $composerJsonFileAsArray['require'] = [];

        printMessage('    Missing ' . var_export('require', true) . ' entry in composer.json file. Creating...');
    }

    printMessage('    vendorName not found. Adding in ' . var_export('require', true) . ' entry...');

    $composerJsonFileAsArray['require'][$vendorName] = '@dev';

    return $composerJsonFileAsArray;
}

/**
 * Create relative path entry in 'repositories' config
 *
 * @param array $composerJsonFileAsArray
 * @param string $relativePath
 * @return array
 */
function composerCreateRepositoryWithRelativePath($composerJsonFileAsArray, $relativePath)
{
    $relativePath = preg_replace('/\\' . DIRECTORY_SEPARATOR . '+/', DIRECTORY_SEPARATOR, $relativePath . DIRECTORY_SEPARATOR);

    printMessage('Creating repository for relativePath ' . var_export($relativePath, true) . '...', 'success');

    if (!array_key_exists('repositories', $composerJsonFileAsArray)) {
        $composerJsonFileAsArray['repositories'] = [];

        printMessage('    Missing ' . var_export('repositories', true) . ' entry in composer.json file. Creating...');
    }
    if (!is_array($composerJsonFileAsArray['repositories'])) {
        $composerJsonFileAsArray['repositories'] = [];

        printMessage('    ' . var_export('repositories', true) . ' entry semms to be invalid in composer.json file. Fixing...');
    }

    $found = false;

    foreach ($composerJsonFileAsArray['repositories'] as $repository) {
        if (array_key_exists('type', $repository) && $repository['type'] === 'path') {
            if (array_key_exists('url', $repository) && $repository['url'] === $relativePath) {
                $found = true;

                printMessage('    Repository already exists. Skipping...');
            }
        }
    }

    if ($found === false) {
        printMessage('    Repository not found. Adding...');

        array_unshift($composerJsonFileAsArray['repositories'], [
            'type' => 'path',
            'url' => $relativePath
        ]);
    }

    return $composerJsonFileAsArray;
}

/**
 * Write new composer.json file contents
 *
 * @param array $composerJsonFileAsArray
 * @return bool|int
 */
function composerWriteComposerJson($composerJsonFileAsArray)
{
    return file_put_contents(
        composerGetComposerJsonFilePath(),
        json_encode($composerJsonFileAsArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}

// ====================================================================================
// run!

if (in_array('--help', $argv)) {
    printMessage('You ask composer-dev-switcher help. There it is :)');
    printMessage('');

    printHelp();

    exit(1);
}

if ($argc !== 2) {
    printError('Expected 1 argument. ' . ($argc - 1) . ' given.');
}

$relativePath = $argv[1];

// composer.json exists?
if (!file_exists(composerGetComposerJsonFilePath())) {
    printError('composer.json file not found.');
}
// composer.json readable?
if (!is_readable(composerGetComposerJsonFilePath())) {
    printError('composer.json file is not readable.');
}
// composer.json writable?
if (!is_writable(composerGetComposerJsonFilePath())) {
    printError('composer.json file is not writable.');
}

$composerJsonFileAsArray = composerGetComposerJsonAsArray(composerGetComposerJsonFilePath());
$vendorName = composerGetVendorNameByRelativePath($relativePath);

$composerJsonFileAsArray = composerCreateRepositoryWithRelativePath($composerJsonFileAsArray, $relativePath);

$composerJsonFileAsArray = composerUpdateVendorNameAsDev($composerJsonFileAsArray, $vendorName);

composerWriteComposerJson($composerJsonFileAsArray);

printMessage('Done.', 'success');

exit(0);
