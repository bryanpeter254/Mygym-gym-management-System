<?php
/*
 * PHP QR Code encoder
 *
 * Simplified QR code implementation by ANTML for Replit use
 */

define('QR_ECLEVEL_L', 0);
define('QR_ECLEVEL_M', 1);
define('QR_ECLEVEL_Q', 2);
define('QR_ECLEVEL_H', 3);

class QRcode {
    public static function png($text, $outfile = false, $level = QR_ECLEVEL_L, $size = 3, $margin = 4) {
        // This is a simplified version that uses QR Code API to generate QR codes
        $apiUrl = 'https://api.qrserver.com/v1/create-qr-code/';
        
        // Map error correction levels
        $ecLevel = 'L';
        switch ($level) {
            case QR_ECLEVEL_M:
                $ecLevel = 'M';
                break;
            case QR_ECLEVEL_Q:
                $ecLevel = 'Q';
                break;
            case QR_ECLEVEL_H:
                $ecLevel = 'H';
                break;
        }
        
        // Calculate size in pixels (multiply by 30 to get reasonable size)
        $pixelSize = max(100, min(1000, $size * 30)); // Between 100 and 1000 pixels
        
        $queryParams = http_build_query([
            'size' => $pixelSize . 'x' . $pixelSize,
            'ecc' => $ecLevel,
            'margin' => $margin,
            'data' => $text
        ]);
        
        $url = $apiUrl . '?' . $queryParams;
        
        // Log the QR generation attempt
        error_log("Generating QR code with URL: " . $url);
        
        // Create the directory if it doesn't exist (for outfile)
        if ($outfile !== false) {
            $outdir = dirname($outfile);
            if (!is_dir($outdir) && !mkdir($outdir, 0755, true)) {
                error_log("Failed to create directory: " . $outdir);
                return false;
            }
        }
        
        // Try with curl first
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $output = curl_exec($ch);
            
            // Check for errors
            if (curl_errno($ch)) {
                error_log("Curl error: " . curl_error($ch));
            }
            
            curl_close($ch);
            
            // Check if curl was successful
            if ($output !== false) {
                if ($outfile !== false) {
                    if (file_put_contents($outfile, $output) !== false) {
                        error_log("QR code saved to: " . $outfile);
                        return true;
                    } else {
                        error_log("Failed to save QR code to: " . $outfile);
                        return false;
                    }
                } else {
                    header('Content-Type: image/png');
                    echo $output;
                    return true;
                }
            }
        }
        
        // Fallback to file_get_contents if curl failed or is not available
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        
        $output = @file_get_contents($url, false, $context);
        
        if ($output === false) {
            error_log("Failed to get QR code using file_get_contents from: " . $url);
            return false;
        }
        
        if ($outfile !== false) {
            if (file_put_contents($outfile, $output) !== false) {
                error_log("QR code saved to: " . $outfile);
                return true;
            } else {
                error_log("Failed to save QR code to: " . $outfile);
                return false;
            }
        } else {
            header('Content-Type: image/png');
            echo $output;
            return true;
        }
    }
}