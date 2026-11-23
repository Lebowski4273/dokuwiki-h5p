<?php
/**
 * Options for the h5p plugin
 */

$meta['libmanagers']     = ['string'];
$meta['strip_on_upload'] = ['onoff'];
$meta['frame']           = ['onoff'];
$meta['copyright']       = ['onoff'];
$meta['fullscreen']      = ['onoff'];
$meta['icon']            = ['onoff'];
$meta['export']          = ['onoff'];
$meta['maxsize']         = ['numeric', '_min' => 1];
$meta['storagedir']      = ['string'];
$meta['replace']         = ['onoff'];
$meta['hub']             = ['onoff'];
$meta['minor_fallback']  = ['onoff'];
