<?php
/**
 * Copyright (C) 2010-2011 Graham Breach
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * For more information, please contact <graham@goat1000.com>
 */

require_once('SVGGraphGridGraph.php');

/**
 * Abstract base class for graphs which use markers
 */
abstract class PointGraph extends GridGraph {

	protected $marker_size = 5;
	protected $marker_type = 'circle';
	protected $marker_colour = NULL;

	private $markers = array();
	private $marker_attrs = array();

	/**
	 * Changes to crosshair cursor by overlaying a transparent rectangle
	 */
	protected function CrossHairs()
	{
		$rect = array(
			'width' => $this->width, 'height' => $this->height,
			'opacity' => 0.0, 'cursor' => 'crosshair'
		);
		return $this->Element('rect', $rect);
	}


	/**
	 * Adds a marker to the list
	 */
	protected function AddMarker($x, $y, $key, $value, $extra = NULL)
	{
		$this->markers[] = new Marker($x, $y, $key, $value, $extra);
	}

	/**
	 * Adds an attribute common to all markers
	 */
	protected function AddMarkerAttr($key, $value)
	{
		$this->marker_attrs[$key] = $value;
	}

	/**
	 * Draws (linked) markers on the graph
	 */
	protected function DrawMarkers()
	{
		if($this->marker_size == 0 || count($this->markers) == 0)
			return '';

		$marker = array('id' => 'lMrk', 'cursor' => 'crosshair');
		$marker = array_merge($this->marker_attrs, $marker);
		if(!is_null($this->marker_colour))
			$marker['fill'] = !is_null($this->marker_colour) ? $this->marker_colour : $this->GetColour(0, true);

		switch($this->marker_type) {
		case 'triangle' :
			$type = 'path';
			$a = $this->marker_size;
			$o = $a * tan(pi() / 6);
			$h = $a / cos(pi() / 6);
			$marker['d'] = "M$a,$o L0,-$h L-$a,$o z";
			break;
		case 'square' :
			$type = 'rect';
			$marker['x'] = $marker['y'] = -$this->marker_size;
			$marker['width'] = $marker['height'] = $this->marker_size * 2;
			break;
		case 'circle' :
		default :
			$type = 'circle';
			$marker['r'] = $this->marker_size;
		}

		// add marker symbol to defs area
		$this->defs[] = $this->Element('symbol', NULL, NULL, $this->Element($type, $marker, NULL));

		$markers = '';
		$linked = false;
		foreach($this->markers as $m) {
			$use = array('x' => $m->x, 'y' => $m->y, 'xlink:href' => '#lMrk');
			if(is_array($m->extra))
				$use = array_merge($m->extra, $use);
			if($this->show_tooltips)
				$this->SetTooltip($use, $m->key, $m->value);


			if($this->GetLinkURL($m->key)) {
				$use['xlink:href'] = '#lMrkA';
				$markers .= $this->GetLink($m->key, $this->Element('use', $use));
				$linked = true;
			} else {
				$markers .= $this->Element('use', $use);
			}
		}
		if($linked) {
			// add a link without crosshair
			unset($marker['cursor']);
			$marker['id'] = 'lMrkA';
			$this->defs[] = $this->Element('symbol', NULL, NULL, $this->Element($type, $marker, NULL));
		}
		return $markers;
	}

}

class Marker {

	public $x, $y, $key, $value, $extra;

	public function __construct($x, $y, $k, $v, $extra)
	{
		$this->x = $x;
		$this->y = $y;
		$this->key = $k;
		$this->value = $v;
		$this->extra = $extra;
	}
}

