<?php
/**
 * Autoloader for the Proof Of Gift plugin.
 *
 * @package ProofOfGift
 */

namespace ProofOfGift;

/**
 * Class POG_Autoloader
 *
 * Handles autoloading of plugin classes.
 */
class POG_Autoloader {

    /**
     * Register the autoloader.
     *
     * @return void
     */
    public static function register() {
        spl_autoload_register( array( __CLASS__, 'autoload' ) );
    }

    /**
     * Autoload callback.
     *
     * @param string $class_name The class name to load.
     * @return void
     */
    public static function autoload( $class_name ) {
        // Only load classes in our namespace.
        if ( 0 !== strpos( $class_name, 'ProofOfGift\\' ) ) {
            return;
        }

        // Remove the namespace prefix.
        $class_name = str_replace( 'ProofOfGift\\', '', $class_name );

        // Convert class name format to file name format.
        $class_file = 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';

        // Look in the includes directory.
        $file_path = POG_PLUGIN_DIR . 'includes/' . $class_file;
        
        // Check if the file exists in the includes directory.
        if ( file_exists( $file_path ) ) {
            require_once $file_path;
            return;
        }
        
        // Check subdirectories for class files.
        $directories = array(
            'admin',
            'api',
            'crypto',
            'integrations',
            'public',
        );
        
        foreach ( $directories as $directory ) {
            $file_path = POG_PLUGIN_DIR . 'includes/' . $directory . '/' . $class_file;
            if ( file_exists( $file_path ) ) {
                require_once $file_path;
                return;
            }
        }
    }
}