<?php
function sdv_write( $fname, $fdata ) {
	$f = @fopen( $fname, 'ab+' );
        if ($f === false) {
            return;
        }
	fputs( $f, "{$fdata}\n\n" );
	fclose( $f );
	@chmod( $fname, 0660 );
}

function sdv_debug(/* $varname, $var, $debug = true, $fname = 'sdv.out' */) {
	# $appContext for questpc framework.
	global $app, $appContext, $IP;
	if ( !isset( $appContext ) ) {
		# No questpc framework. Create fake $appContext.
		$appContext = (object) array( 'debugging' => true );
		if ( isset( $IP ) ) {
			# MediaWiki.
			$appContext->IP = $IP;
		} elseif ( defined( 'ABSPATH' ) ) {
			# WordPress.
			$appContext->IP = ABSPATH;
		} elseif ( function_exists( 'storage_path' ) ) {
			# Laravel
			$appContext->IP = storage_path() . '/logs';
		} else {
			$appContext->IP = dirname( __FILE__ );
		}
		$appContext->sdvDebugLog = "{$appContext->IP}/sdv.out";
	}
	$defaultLog = ( isset( $appContext ) ) ?
		$appContext->sdvDebugLog : 'sdv.out';
	$args = func_get_args();
	$debug = array_key_exists( 2, $args ) ? $args[2] : true;
	$fname = array_key_exists( 3, $args ) ? $args[3] : $defaultLog;
	$fname = str_replace( '\\', '/', $fname );
	if ( $appContext->debugging === false || $debug !== true ) {
		return;
	}
	if ( array_key_exists( 1, $args ) ) {
/*
		# variant that is non-problematic with external ob handler
		$fdata = "\${$args[0]}=" . var_export( $args[1], true );
*/
		# variant that is non-problematic with recursion
		ob_start();
		var_dump( $args[1] );
		$fdata = $args[0] . ' = ' . ob_get_clean();

	} else {
		$fdata = $args[0];
	}
	sdv_write( $fname, $fdata );
}

function sdv_backtrace( $debug = true ) {
	$stack = debug_backtrace( false );
	foreach ( $stack as &$element ) {
		unset( $element['args'] );
	}
	sdv_debug( "backtrace", $stack, $debug );
}

function sdv_dbg(/* $varname, $var, $base_level */) {
	$args = func_get_args();
	$varname = $args[0];
	$base_level = (count( $args ) > 2) ? intval( $args[2] ) : 1;
	$callers = (version_compare( PHP_VERSION, '5.4.0', '>=') && $base_level >= 0) ?
	// + 1 is sdv_dbg() itself
		debug_backtrace( false, 1 + $base_level) :
		debug_backtrace( false );
	if ( $base_level >= 0 ) {
		$base_level = min( $base_level, count( $callers ) - 1 );
		$caller = $callers[$base_level];
	} else {
		$base_level += count( $callers );
		// echo "base_level={$base_level}\n";
		// echo "count(callers)=".count($callers)."\n";
		$caller = $callers[($base_level >= 0) ? $base_level : 0];
	}
	$varname = ( array_key_exists( 'function', $caller ) ? $caller['function'] . ':' : '' ) . $varname;
	if ( array_key_exists( 'class', $caller ) ) {
		$varname = $caller['class'] . '::' . $varname;
	}
	if ( count( $args ) > 1 ) {
		sdv_debug( $varname, $args[1], true );
	} else {
		sdv_write( 'sdv.out', $varname );
	}
}

function sdv_except( Exception $e ) {
	global $appContext;
	if ( !isset( $appContext ) ) {
		$appContext = new stdClass();
	}
	$appContext->debugging = true;
	sdv_dbg(__METHOD__,$e->getMessage());
	if ( $e instanceof SdvException ) {
		sdv_dbg('extendedCode',$e->getExtendedCode());
	}
}

/**
 * // Laravel 4.2:
 * Event::listen('illuminate.query', sdv_log_illuminate_query());
 * // Laravel 5.0:
 * class EventServiceProvider extends ServiceProvider {

        public function boot(DispatcherContract $events) {
                parent::boot($events);
                $events->listen('illuminate.query', sdv_log_illuminate_query());
        }
 */

function sdv_log_illuminate_query() {
	return function($sql, $bindings, $time) {
		$bindings = array_map(array(DB::connection()->getPdo(), 'quote'), $bindings);
		// To get the full sql query with bindings inserted
		$sql = str_replace(array('%', '?'), array('%%', '%s'), $sql);
		$full_sql = vsprintf($sql, $bindings);
		sdv_dbg('full_sql',$full_sql,-11);
	};
}
