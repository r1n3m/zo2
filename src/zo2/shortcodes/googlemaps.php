<?php
/**
 * Zo2 (http://www.zo2framework.org)
 * A powerful Joomla template framework
 *
 * @link        http://www.zo2framework.org
 * @link        http://github.com/aploss/zo2
 * @author      ZooTemplate <http://zootemplate.com>
 * @copyright   Copyright (c) 2013 APL Solutions (http://apl.vn)
 * @license     GPL v2
 */
//no direct accees
defined('_JEXEC') or die ('resticted aceess');

Zo2Framework::import2('core.Zo2Shortcode');

class Googlemaps extends Zo2Shortcode
{
    // set short code tag
    protected $tagname = 'googlemaps';

    protected $embed = true;

    /**
     * Overwrites the parent method
     * @return string the embed HTML
     */
    protected function body()
    {

        // initializing variables for short code
        extract($this->shortcode_atts(array(
                'lat' => -34.397,
                'lng' => 150.644,
                'zoom' => 11,
                'w' => 100,
                'h' => 400
            ),
            $this->attrs
        ));

        $w = ($w == '100%') ? $w : $w . 'px';

        $script = '

            var map;

            function initialize() {
              var myLatlng = new google.maps.LatLng(' . $lat . ', ' . $lng . ');
              var mapOptions = {
                zoom: ' . $zoom . ',
                center: myLatlng,
                mapTypeId: google.maps.MapTypeId.ROADMAP
              };
              map = new google.maps.Map(document.getElementById(\'map-canvas\'), mapOptions);
              var marker = new google.maps.Marker({
                            position: myLatlng,
                            title: "' . $this->content . '"
                           });
              marker.setMap(map);
            }

            google.maps.event.addDomListener(window, \'load\', initialize);

        ';

        return '<div id="map-canvas" style="width: ' . $w . '; height: ' . $h . 'px;"></div>
                <script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false"></script>
                <script type="text/javascript">'.$script.'</script>
                ';
    }

}
