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
	private $marker_ids = array();
	private $marker_used = array();
	private $marker_elements = array();
	private $marker_types = array();

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
	protected function AddMarker($x, $y, $key, $value, $extra = NULL, $set = 0)
	{
		$this->markers[$set][] = new Marker($x, $y, $key, $value, $extra);
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

		$this->CreateMarkers();

		$markers = '';
		foreach($this->markers as $set => $data) {
			if($this->marker_ids[$set] && count($data))
				$markers .= $this->DrawMarkerSet($set, $data);
		}
		foreach(array_keys($this->marker_used) as $id) {
			$this->defs[] = $this->marker_elements[$id];
		}
		return $markers;
	}

	/**
	 * Draws a single set of markers
	 * */
	protected function DrawMarkerSet($set, &$marker_data)
	{
		$markers = '';
		foreach($marker_data as $m) {
			$markers .= $this->GetMarker($m, $set);
		}
		return $markers;
	}


	/**
	 * Returns a marker element
	 */
	private function GetMarker($marker, $set)
	{
		$id = $this->marker_ids[$set];
		$use = array('x' => $marker->x, 'y' => $marker->y, 'xlink:href' => '#' . $id);
		if(is_array($marker->extra))
			$use = array_merge($marker->extra, $use);
		if($this->show_tooltips)
			$this->SetTooltip($use, $marker->key, $marker->value);

		if($this->GetLinkURL($marker->key)) {
			$id .= 'A';
			$use['xlink:href'] .= 'A';
			$element = $this->GetLink($marker->key, $this->Element('use', $use));
		} else {
			$element = $this->Element('use', $use);
		}
		if(!isset($this->marker_used[$id]))
			$this->marker_used[$id] = 1;
		return $element;
	}

	/**
	 * Creates the marker types
	 */
	private function CreateMarkers()
	{
		foreach(array_keys($this->markers) as $set) {
			$id = 'lMrk' . $set;
			$marker = array('id' => $id, 'cursor' => 'crosshair');
			$marker = array_merge($this->marker_attrs, $marker);

			$type = is_array($this->marker_type) ?
				$this->marker_type[$set % count($this->marker_type)] :
				$this->marker_type;
			$size = is_array($this->marker_size) ?
				$this->marker_size[$set % count($this->marker_size)] :
				$this->marker_size;
			if(isset($this->marker_colour)) {
				$marker['fill'] = is_array($this->marker_colour) ?
					$this->marker_colour[$set % count($this->marker_colour)] :
					$this->marker_colour;
			} else {
				$marker['fill'] = $this->GetColour($set, true);
			}

			$m_key = "$type:$size:{$marker['fill']}";
			if(isset($this->marker_types[$m_key])) {
				$this->marker_ids[$set] = $this->marker_types[$m_key];
			} else {

				$a = $size; // will be repeated a lot, and 'a' is smaller
				switch($type) {
				case 'triangle' :
					$type = 'path';
					$o = $a * tan(M_PI / 6);
					$h = $a / cos(M_PI / 6);
					$marker['d'] = "M$a,$o L0,-$h L-$a,$o z";
					break;
				case 'square' :
					$type = 'rect';
					$marker['x'] = $marker['y'] = -$a;
					$marker['width'] = $marker['height'] = $a * 2;
					break;
				case 'x' :
					$marker['transform'] = 'rotate(45)';
					// no break - 'x' is a cross rotated by 45 degrees
				case 'cross' :
					$type = 'path';
					$t = $a / 4;
					$marker['d'] = "M-$a,-$t L-$a,$t L-$t,$t L-$t,$a L$t,$a L$t,$t L$a,$t L$a,-$t L$t,-$t L$t,-$a L-$t,-$a L-$t,-$t z";
					break;
				case 'pentagon' :
					$type = 'path';
					$x1 = $a * sin(M_PI * 0.4);
					$y1 = $a * cos(M_PI * 0.4);
					$x2 = $a * sin(M_PI * 0.2);
					$y2 = $a * cos(M_PI * 0.2);
					$marker['d'] = "M0,-$a L$x1,-$y1 L$x2,$y2 L-$x2,$y2 L-$x1,-$y1 z";
					break;
				case 'circle' :
				default :
					$type = 'circle';
					$marker['r'] = $size;
				}

				$this->marker_elements[$marker['id']] = $this->Element('symbol', NULL, NULL, $this->Element($type, $marker, NULL));

				// add link version
				unset($marker['cursor']);
				$marker['id'] .= 'A';
				$this->marker_elements[$marker['id']] = $this->Element('symbol', NULL, NULL, $this->Element($type, $marker, NULL));

				// set the ID for this data set to use
				$this->marker_ids[$set] = $id;

				// save this marker style for reuse
				$this->marker_types[$m_key] = $id;
			}
		}
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

