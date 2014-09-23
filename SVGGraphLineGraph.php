<?php
/**
 * Copyright (C) 2009-2011 Graham Breach
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

require_once('SVGGraphPointGraph.php');

/**
 * LineGraph - joined line, with axes and grid
 */
class LineGraph extends PointGraph {

	protected $line_stroke_width = 2;
	protected $line_dash = null;
	protected $fill_under = false;
	protected $fill_opacity = 1;

	public function Draw()
	{
		$assoc = $this->AssociativeKeys();
		$this->CalcAxes($assoc);
		$body = $this->Grid();

		$attr = array('stroke' => $this->stroke_colour, 'fill' => 'none');
		$dash = is_array($this->line_dash) ? $this->line_dash[0] : $this->line_dash;
		$stroke_width = is_array($this->line_stroke_width) ? $this->line_stroke_width[0] : $this->line_stroke_width;
		if(!is_null($dash))
			$attr['stroke-dasharray'] = $dash;
		$attr['stroke-width'] = $stroke_width <= 0 ? 1 : $stroke_width;

		$bnum = 0;
		$cmd = 'M';
		$ccount = count($this->colours);
		$path = '';
		if($this->fill_under) {
			$cmd = 'L';
			$attr['fill'] = $this->GetColour(0);
			if($this->fill_opacity < 1.0)
				$attr['fill-opacity'] = $this->fill_opacity;
		}

		$values = $this->GetValues();
		foreach($values as $key => $value) {
			$point_pos = $this->GridPosition($key, $bnum);
			if(!is_null($value) && !is_null($point_pos)) {
				$x = $point_pos;
				$y = $this->height - $this->pad_bottom - $this->y0 - ($value * $this->bar_unit_height);

				if($this->fill_under && $path == '')
					$path = 'M' . $x . ' ' . ($this->height - $this->pad_bottom - $this->y0);

				$path .= "$cmd$x $y ";

				// no need to repeat same L command
				$cmd = $cmd == 'M' ? 'L' : '';
				$this->AddMarker($x, $y, $key, $value);
			}
			++$bnum;
		}

		if($this->fill_under)
			$path .= $cmd . $x . ' ' . ($this->height - $this->pad_bottom - $this->y0) . 'z';

		$attr['d'] = $path;
		$group = array();
		$this->ClipGrid($group);
		$body .= $this->Element('g', $group, NULL, $this->Element('path', $attr));

		$body .= $this->Axes();
		$body .= $this->CrossHairs();
		$body .= $this->DrawMarkers();
		return $body;
	}

	protected function CheckValues(&$values)
	{
		parent::CheckValues($values);

		if(count($values[0]) <= 1)
			throw new Exception('Not enough values for line graph');
	}
}

