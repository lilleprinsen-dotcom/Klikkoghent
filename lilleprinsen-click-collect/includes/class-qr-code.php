<?php
/**
 * Local QR code generation.
 *
 * @package Lilleprinsen_Click_Collect
 */

declare( strict_types=1 );

namespace Lilleprinsen\ClickCollect;

use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds terminal QR URLs and renders local SVG QR codes.
 */
final class QR_Code {
	private const VERSION             = 10;
	private const SIZE                = 57;
	private const DATA_CODEWORDS      = 274;
	private const ERROR_CODEWORDS     = 18;
	private const ERROR_BLOCKS        = 4;
	private const FORMAT_XOR          = 0x5412;
	private const FORMAT_GENERATOR    = 0x537;
	private const VERSION_GENERATOR   = 0x1f25;
	private const LOW_ERROR_LEVEL_BITS = 1;

	/**
	 * Generate a secure random token for pickup QR URLs.
	 */
	public function generate_token(): string {
		try {
			return bin2hex( random_bytes( 32 ) );
		} catch ( \Exception $exception ) {
			return wp_generate_password( 64, false, false );
		}
	}

	/**
	 * Build the terminal URL encoded in the QR code.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	public function get_terminal_url( WC_Order $order ): string {
		$pickup_number = (string) $order->get_meta( Order_Helper::META_PICKUP_NUMBER, true );
		$qr_token      = (string) $order->get_meta( Order_Helper::META_QR_TOKEN, true );
		$terminal_slug = sanitize_title( (string) Settings::get( 'terminal_slug' ) );

		if ( '' === $terminal_slug ) {
			$terminal_slug = 'butikkterminal';
		}

		return add_query_arg(
			array(
				'pickup' => $pickup_number,
				'token'  => $qr_token,
			),
			home_url( '/' . $terminal_slug )
		);
	}

	/**
	 * Render an SVG QR code for a URL.
	 *
	 * @param string $url URL to encode.
	 * @return string SVG markup.
	 */
	public function render_svg( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			throw new \InvalidArgumentException( 'QR URL is empty.' );
		}

		$matrix = $this->encode_url_to_matrix( $url );
		$size   = self::SIZE + 8;
		$path   = array();

		foreach ( $matrix as $y => $row ) {
			foreach ( $row as $x => $dark ) {
				if ( $dark ) {
					$path[] = 'M' . ( $x + 4 ) . ',' . ( $y + 4 ) . 'h1v1h-1z';
				}
			}
		}

		return sprintf(
			'<svg class="lp-cc-qr-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" role="img" aria-label="%2$s"><rect width="100%%" height="100%%" fill="#fff"/><path fill="#111827" d="%3$s"/></svg>',
			$size,
			esc_attr__( 'QR-kode for butikkterminal', 'lilleprinsen-click-collect' ),
			esc_attr( implode( '', $path ) )
		);
	}

	/**
	 * Encode a URL into a fixed-version QR matrix.
	 *
	 * @param string $url URL to encode.
	 * @return array<int, array<int, bool>>
	 */
	private function encode_url_to_matrix( string $url ): array {
		$bytes = unpack( 'C*', $url );
		$bytes = is_array( $bytes ) ? array_values( $bytes ) : array();

		if ( count( $bytes ) > self::DATA_CODEWORDS - 3 ) {
			throw new \LengthException( 'QR URL is too long for the local renderer.' );
		}

		$data_codewords = $this->build_data_codewords( $bytes );
		$all_codewords  = $this->add_error_correction( $data_codewords );

		return $this->draw_matrix( $all_codewords );
	}

	/**
	 * Build byte-mode QR data codewords.
	 *
	 * @param array<int, int> $bytes URL bytes.
	 * @return array<int, int>
	 */
	private function build_data_codewords( array $bytes ): array {
		$bits = array();
		$this->append_bits( $bits, 0x4, 4 );
		$this->append_bits( $bits, count( $bytes ), 16 );

		foreach ( $bytes as $byte ) {
			$this->append_bits( $bits, $byte, 8 );
		}

		$capacity_bits = self::DATA_CODEWORDS * 8;
		$terminator   = min( 4, $capacity_bits - count( $bits ) );
		$this->append_bits( $bits, 0, $terminator );

		while ( 0 !== count( $bits ) % 8 ) {
			$bits[] = 0;
		}

		$data = array();
		for ( $i = 0; $i < count( $bits ); $i += 8 ) {
			$value = 0;
			for ( $j = 0; $j < 8; $j++ ) {
				$value = ( $value << 1 ) | $bits[ $i + $j ];
			}
			$data[] = $value;
		}

		$pad = 0xec;
		while ( count( $data ) < self::DATA_CODEWORDS ) {
			$data[] = $pad;
			$pad    = 0xec === $pad ? 0x11 : 0xec;
		}

		return $data;
	}

	/**
	 * Append bits most significant bit first.
	 *
	 * @param array<int, int> $bits Bit stream.
	 * @param int             $value Value.
	 * @param int             $length Number of bits.
	 */
	private function append_bits( array &$bits, int $value, int $length ): void {
		for ( $i = $length - 1; $i >= 0; $i-- ) {
			$bits[] = ( $value >> $i ) & 1;
		}
	}

	/**
	 * Add Reed-Solomon error correction and interleave QR blocks.
	 *
	 * @param array<int, int> $data Data codewords.
	 * @return array<int, int>
	 */
	private function add_error_correction( array $data ): array {
		$blocks         = array();
		$short_data_len = 68;
		$offset         = 0;
		$generator      = $this->reed_solomon_generator( self::ERROR_CODEWORDS );

		for ( $block_index = 0; $block_index < self::ERROR_BLOCKS; $block_index++ ) {
			$data_len   = $block_index < 2 ? $short_data_len : $short_data_len + 1;
			$block_data = array_slice( $data, $offset, $data_len );
			$offset    += $data_len;
			$blocks[]   = array(
				'data' => $block_data,
				'ecc'  => $this->reed_solomon_remainder( $block_data, $generator ),
			);
		}

		$result = array();
		for ( $i = 0; $i < $short_data_len + 1; $i++ ) {
			foreach ( $blocks as $block_index => $block ) {
				if ( 2 > $block_index && $i === $short_data_len ) {
					continue;
				}

				$result[] = $block['data'][ $i ];
			}
		}

		for ( $i = 0; $i < self::ERROR_CODEWORDS; $i++ ) {
			foreach ( $blocks as $block ) {
				$result[] = $block['ecc'][ $i ];
			}
		}

		return $result;
	}

	/**
	 * Return a Reed-Solomon generator polynomial.
	 *
	 * @param int $degree Degree.
	 * @return array<int, int>
	 */
	private function reed_solomon_generator( int $degree ): array {
		$result               = array_fill( 0, $degree, 0 );
		$result[ $degree - 1 ] = 1;
		$root                 = 1;

		for ( $i = 0; $i < $degree; $i++ ) {
			for ( $j = 0; $j < $degree; $j++ ) {
				$result[ $j ] = $this->gf_multiply( $result[ $j ], $root );

				if ( $j + 1 < $degree ) {
					$result[ $j ] ^= $result[ $j + 1 ];
				}
			}

			$root = $this->gf_multiply( $root, 0x02 );
		}

		return $result;
	}

	/**
	 * Return the Reed-Solomon remainder.
	 *
	 * @param array<int, int> $data Data codewords.
	 * @param array<int, int> $generator Generator polynomial.
	 * @return array<int, int>
	 */
	private function reed_solomon_remainder( array $data, array $generator ): array {
		$result = array_fill( 0, count( $generator ), 0 );

		foreach ( $data as $byte ) {
			$factor = $byte ^ array_shift( $result );
			$result[] = 0;

			foreach ( $generator as $i => $coefficient ) {
				$result[ $i ] ^= $this->gf_multiply( $coefficient, $factor );
			}
		}

		return $result;
	}

	/**
	 * Multiply two numbers in GF(2^8).
	 */
	private function gf_multiply( int $x, int $y ): int {
		$result = 0;

		for ( ; 0 !== $y; $y >>= 1 ) {
			if ( 0 !== ( $y & 1 ) ) {
				$result ^= $x;
			}

			$x <<= 1;
			if ( 0 !== ( $x & 0x100 ) ) {
				$x ^= 0x11d;
			}
		}

		return $result & 0xff;
	}

	/**
	 * Draw QR function patterns, data, mask, and metadata.
	 *
	 * @param array<int, int> $codewords Data plus error correction.
	 * @return array<int, array<int, bool>>
	 */
	private function draw_matrix( array $codewords ): array {
		$modules  = array_fill( 0, self::SIZE, array_fill( 0, self::SIZE, false ) );
		$function = array_fill( 0, self::SIZE, array_fill( 0, self::SIZE, false ) );

		$this->draw_function_patterns( $modules, $function );
		$this->draw_version_bits( $modules, $function );
		$this->draw_codewords( $modules, $function, $codewords );
		$this->apply_mask( $modules, $function, 0 );
		$this->draw_format_bits( $modules, $function, 0 );

		return $modules;
	}

	/**
	 * Draw fixed QR function patterns.
	 *
	 * @param array<int, array<int, bool>> $modules Matrix.
	 * @param array<int, array<int, bool>> $function Function mask.
	 */
	private function draw_function_patterns( array &$modules, array &$function ): void {
		$this->draw_finder_pattern( $modules, $function, 3, 3 );
		$this->draw_finder_pattern( $modules, $function, self::SIZE - 4, 3 );
		$this->draw_finder_pattern( $modules, $function, 3, self::SIZE - 4 );

		for ( $i = 8; $i < self::SIZE - 8; $i++ ) {
			$this->set_function_module( $modules, $function, 6, $i, 0 === $i % 2 );
			$this->set_function_module( $modules, $function, $i, 6, 0 === $i % 2 );
		}

		foreach ( array( 6, 28, 50 ) as $x ) {
			foreach ( array( 6, 28, 50 ) as $y ) {
				if ( ( 6 === $x && 6 === $y ) || ( 6 === $x && 50 === $y ) || ( 50 === $x && 6 === $y ) ) {
					continue;
				}

				$this->draw_alignment_pattern( $modules, $function, $x, $y );
			}
		}

		$this->set_function_module( $modules, $function, 8, self::SIZE - 8, true );

		for ( $i = 0; $i < 9; $i++ ) {
			$this->set_function_module( $modules, $function, 8, $i, false );
			$this->set_function_module( $modules, $function, $i, 8, false );
		}

		for ( $i = 0; $i < 8; $i++ ) {
			$this->set_function_module( $modules, $function, self::SIZE - 1 - $i, 8, false );
			$this->set_function_module( $modules, $function, 8, self::SIZE - 1 - $i, false );
		}
	}

	/**
	 * Draw one finder pattern.
	 *
	 * @param array<int, array<int, bool>> $modules Matrix.
	 * @param array<int, array<int, bool>> $function Function mask.
	 * @param int                          $center_x Center X.
	 * @param int                          $center_y Center Y.
	 */
	private function draw_finder_pattern( array &$modules, array &$function, int $center_x, int $center_y ): void {
		for ( $dy = -4; $dy <= 4; $dy++ ) {
			for ( $dx = -4; $dx <= 4; $dx++ ) {
				$x = $center_x + $dx;
				$y = $center_y + $dy;

				if ( $x < 0 || $x >= self::SIZE || $y < 0 || $y >= self::SIZE ) {
					continue;
				}

				$distance = max( abs( $dx ), abs( $dy ) );
				$this->set_function_module( $modules, $function, $x, $y, 2 !== $distance && 4 !== $distance );
			}
		}
	}

	/**
	 * Draw one alignment pattern.
	 *
	 * @param array<int, array<int, bool>> $modules Matrix.
	 * @param array<int, array<int, bool>> $function Function mask.
	 * @param int                          $center_x Center X.
	 * @param int                          $center_y Center Y.
	 */
	private function draw_alignment_pattern( array &$modules, array &$function, int $center_x, int $center_y ): void {
		for ( $dy = -2; $dy <= 2; $dy++ ) {
			for ( $dx = -2; $dx <= 2; $dx++ ) {
				$this->set_function_module(
					$modules,
					$function,
					$center_x + $dx,
					$center_y + $dy,
					max( abs( $dx ), abs( $dy ) ) !== 1
				);
			}
		}
	}

	/**
	 * Set a function module.
	 *
	 * @param array<int, array<int, bool>> $modules Matrix.
	 * @param array<int, array<int, bool>> $function Function mask.
	 * @param int                          $x X coordinate.
	 * @param int                          $y Y coordinate.
	 * @param bool                         $dark Dark module.
	 */
	private function set_function_module( array &$modules, array &$function, int $x, int $y, bool $dark ): void {
		if ( $x < 0 || $x >= self::SIZE || $y < 0 || $y >= self::SIZE ) {
			return;
		}

		$modules[ $y ][ $x ]  = $dark;
		$function[ $y ][ $x ] = true;
	}

	/**
	 * Draw data codewords into the QR matrix.
	 *
	 * @param array<int, array<int, bool>> $modules Matrix.
	 * @param array<int, array<int, bool>> $function Function mask.
	 * @param array<int, int>              $codewords Data plus error correction.
	 */
	private function draw_codewords( array &$modules, array $function, array $codewords ): void {
		$bits = array();
		foreach ( $codewords as $codeword ) {
			$this->append_bits( $bits, $codeword, 8 );
		}

		$bit_index = 0;
		for ( $right = self::SIZE - 1; $right >= 1; $right -= 2 ) {
			if ( 6 === $right ) {
				$right--;
			}

			for ( $vertical = 0; $vertical < self::SIZE; $vertical++ ) {
				$y = ( 0 === ( ( $right + 1 ) & 2 ) ) ? self::SIZE - 1 - $vertical : $vertical;

				for ( $j = 0; $j < 2; $j++ ) {
					$x = $right - $j;
					if ( $function[ $y ][ $x ] ) {
						continue;
					}

					$modules[ $y ][ $x ] = isset( $bits[ $bit_index ] ) && 1 === $bits[ $bit_index ];
					$bit_index++;
				}
			}
		}
	}

	/**
	 * Apply QR mask pattern.
	 *
	 * @param array<int, array<int, bool>> $modules Matrix.
	 * @param array<int, array<int, bool>> $function Function mask.
	 * @param int                          $mask Mask number.
	 */
	private function apply_mask( array &$modules, array $function, int $mask ): void {
		for ( $y = 0; $y < self::SIZE; $y++ ) {
			for ( $x = 0; $x < self::SIZE; $x++ ) {
				if ( ! $function[ $y ][ $x ] && $this->mask_applies( $mask, $x, $y ) ) {
					$modules[ $y ][ $x ] = ! $modules[ $y ][ $x ];
				}
			}
		}
	}

	/**
	 * Check whether a mask applies to a coordinate.
	 */
	private function mask_applies( int $mask, int $x, int $y ): bool {
		if ( 0 === $mask ) {
			return 0 === ( ( $x + $y ) % 2 );
		}

		return false;
	}

	/**
	 * Draw QR format bits.
	 *
	 * @param array<int, array<int, bool>> $modules Matrix.
	 * @param array<int, array<int, bool>> $function Function mask.
	 * @param int                          $mask Mask number.
	 */
	private function draw_format_bits( array &$modules, array &$function, int $mask ): void {
		$data = ( self::LOW_ERROR_LEVEL_BITS << 3 ) | $mask;
		$bits = ( $data << 10 ) | $this->bch_remainder( $data << 10, self::FORMAT_GENERATOR );
		$bits ^= self::FORMAT_XOR;

		for ( $i = 0; $i <= 5; $i++ ) {
			$this->set_function_module( $modules, $function, 8, $i, $this->get_bit( $bits, $i ) );
		}
		$this->set_function_module( $modules, $function, 8, 7, $this->get_bit( $bits, 6 ) );
		$this->set_function_module( $modules, $function, 8, 8, $this->get_bit( $bits, 7 ) );
		$this->set_function_module( $modules, $function, 7, 8, $this->get_bit( $bits, 8 ) );
		for ( $i = 9; $i < 15; $i++ ) {
			$this->set_function_module( $modules, $function, 14 - $i, 8, $this->get_bit( $bits, $i ) );
		}

		for ( $i = 0; $i < 8; $i++ ) {
			$this->set_function_module( $modules, $function, self::SIZE - 1 - $i, 8, $this->get_bit( $bits, $i ) );
		}
		for ( $i = 8; $i < 15; $i++ ) {
			$this->set_function_module( $modules, $function, 8, self::SIZE - 15 + $i, $this->get_bit( $bits, $i ) );
		}
	}

	/**
	 * Draw QR version bits.
	 *
	 * @param array<int, array<int, bool>> $modules Matrix.
	 * @param array<int, array<int, bool>> $function Function mask.
	 */
	private function draw_version_bits( array &$modules, array &$function ): void {
		$bits = ( self::VERSION << 12 ) | $this->bch_remainder( self::VERSION << 12, self::VERSION_GENERATOR );

		for ( $i = 0; $i < 18; $i++ ) {
			$dark = $this->get_bit( $bits, $i );
			$x    = self::SIZE - 11 + ( $i % 3 );
			$y    = (int) floor( $i / 3 );

			$this->set_function_module( $modules, $function, $x, $y, $dark );
			$this->set_function_module( $modules, $function, $y, $x, $dark );
		}
	}

	/**
	 * Return BCH remainder for QR format/version metadata.
	 */
	private function bch_remainder( int $value, int $generator ): int {
		$generator_degree = $this->bit_length( $generator ) - 1;

		while ( $this->bit_length( $value ) - 1 >= $generator_degree ) {
			$value ^= $generator << ( $this->bit_length( $value ) - 1 - $generator_degree );
		}

		return $value;
	}

	/**
	 * Return bit length.
	 */
	private function bit_length( int $value ): int {
		$length = 0;
		while ( 0 !== $value ) {
			$length++;
			$value >>= 1;
		}

		return $length;
	}

	/**
	 * Return one bit from a value.
	 */
	private function get_bit( int $value, int $index ): bool {
		return 0 !== ( ( $value >> $index ) & 1 );
	}
}
