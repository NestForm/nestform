<?php
/**
 * Safe calculated-field formula evaluation.
 *
 * Supports + - * / ( ), min(), max(), round(), and {field_name} tokens.
 *
 * @package Nestform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Formula {

	/**
	 * Evaluate a formula against field values.
	 *
	 * @param string               $formula Formula string.
	 * @param array<string, mixed> $data    Field name => value.
	 * @return float|string Empty string on failure.
	 */
	public static function evaluate( $formula, array $data ) {
		$formula = trim( (string) $formula );
		if ( '' === $formula ) {
			return '';
		}

		$expr = preg_replace_callback(
			'/\{([a-zA-Z0-9_]+)\}/',
			static function ( $m ) use ( $data ) {
				$key = $m[1];
				if ( ! array_key_exists( $key, $data ) ) {
					return '0';
				}
				$val = $data[ $key ];
				if ( is_array( $val ) ) {
					return '0';
				}
				if ( is_bool( $val ) ) {
					return $val ? '1' : '0';
				}
				$s = trim( (string) $val );
				if ( '' === $s || ! is_numeric( $s ) ) {
					return '0';
				}
				return (string) ( 0 + $s );
			},
			$formula
		);

		$expr = strtolower( (string) $expr );
		$expr = preg_replace( '/\s+/', '', $expr );
		if ( ! is_string( $expr ) || '' === $expr ) {
			return '';
		}

		if ( ! preg_match( '/^[0-9+\-*\/().,minaxroud]+$/', $expr ) ) {
			return '';
		}

		$result = self::eval_expr( $expr );
		if ( null === $result || ! is_finite( $result ) ) {
			return '';
		}

		// Trim trailing zeros for cleaner display.
		$formatted = rtrim( rtrim( sprintf( '%.8F', $result ), '0' ), '.' );
		return '' === $formatted ? '0' : $formatted;
	}

	/**
	 * @param string $expr Sanitized expression.
	 * @return float|null
	 */
	private static function eval_expr( $expr ) {
		$pos = 0;
		$len = strlen( $expr );
		try {
			$value = self::parse_expression( $expr, $pos, $len );
			if ( $pos !== $len ) {
				return null;
			}
			return $value;
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			return null;
		}
	}

	/**
	 * @param string $expr Expression.
	 * @param int    $pos  Position (by ref).
	 * @param int    $len  Length.
	 * @return float
	 */
	private static function parse_expression( $expr, &$pos, $len ) {
		$value = self::parse_term( $expr, $pos, $len );
		while ( $pos < $len ) {
			$op = $expr[ $pos ];
			if ( '+' !== $op && '-' !== $op ) {
				break;
			}
			++$pos;
			$right = self::parse_term( $expr, $pos, $len );
			$value = '+' === $op ? $value + $right : $value - $right;
		}
		return $value;
	}

	/**
	 * @param string $expr Expression.
	 * @param int    $pos  Position.
	 * @param int    $len  Length.
	 * @return float
	 */
	private static function parse_term( $expr, &$pos, $len ) {
		$value = self::parse_factor( $expr, $pos, $len );
		while ( $pos < $len ) {
			$op = $expr[ $pos ];
			if ( '*' !== $op && '/' !== $op ) {
				break;
			}
			++$pos;
			$right = self::parse_factor( $expr, $pos, $len );
			if ( '/' === $op ) {
				if ( 0.0 === (float) $right ) {
					throw new Exception( 'div0' );
				}
				$value = $value / $right;
			} else {
				$value = $value * $right;
			}
		}
		return $value;
	}

	/**
	 * @param string $expr Expression.
	 * @param int    $pos  Position.
	 * @param int    $len  Length.
	 * @return float
	 */
	private static function parse_factor( $expr, &$pos, $len ) {
		if ( $pos >= $len ) {
			throw new Exception( 'eof' );
		}

		if ( '-' === $expr[ $pos ] ) {
			++$pos;
			return -1 * self::parse_factor( $expr, $pos, $len );
		}
		if ( '+' === $expr[ $pos ] ) {
			++$pos;
			return self::parse_factor( $expr, $pos, $len );
		}

		if ( '(' === $expr[ $pos ] ) {
			++$pos;
			$value = self::parse_expression( $expr, $pos, $len );
			if ( $pos >= $len || ')' !== $expr[ $pos ] ) {
				throw new Exception( 'paren' );
			}
			++$pos;
			return $value;
		}

		foreach ( array( 'min', 'max', 'round' ) as $fn ) {
			$fn_len = strlen( $fn );
			if ( substr( $expr, $pos, $fn_len ) === $fn && ( $pos + $fn_len ) < $len && '(' === $expr[ $pos + $fn_len ] ) {
				$pos += $fn_len + 1;
				$args = array();
				if ( $pos < $len && ')' !== $expr[ $pos ] ) {
					$args[] = self::parse_expression( $expr, $pos, $len );
					while ( $pos < $len && ',' === $expr[ $pos ] ) {
						++$pos;
						$args[] = self::parse_expression( $expr, $pos, $len );
					}
				}
				if ( $pos >= $len || ')' !== $expr[ $pos ] ) {
					throw new Exception( 'fn' );
				}
				++$pos;
				if ( 'min' === $fn ) {
					return (float) min( $args );
				}
				if ( 'max' === $fn ) {
					return (float) max( $args );
				}
				$num  = isset( $args[0] ) ? (float) $args[0] : 0.0;
				$prec = isset( $args[1] ) ? (int) $args[1] : 0;
				return round( $num, max( 0, min( 8, $prec ) ) );
			}
		}

		$start = $pos;
		while ( $pos < $len && ( ctype_digit( $expr[ $pos ] ) || '.' === $expr[ $pos ] ) ) {
			++$pos;
		}
		if ( $start === $pos ) {
			throw new Exception( 'num' );
		}
		return (float) substr( $expr, $start, $pos - $start );
	}
}
