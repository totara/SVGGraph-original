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

require_once('SVGGraphPointGraph.php');

/**
 * ScatterGraph - points with axes and grid
 */
class ScatterGraph extends PointGraph {

	private $max_h = 0;

	public function Draw()
	{
		$this->CalcAxes();
		$body = $this->Grid();
		$values = $this->GetValues();

		// a scatter graph without markers is empty!
		if($this->marker_size == 0)
			$this->marker_size = 1;

		foreach($values as $key => $value) {
			if(!is_null($value)) {
				$x = $this->pad_left + $this->x0 + ($key * $this->bar_unit_width);
				$y = $this->height - $this->pad_bottom - $this->y0 - ($value * $this->bar_unit_height);

				$this->AddMarker($x, $y, $key, $value);
			}
		}

		$body .= $this->Axes();
		$body .= $this->CrossHairs();
		$body .= $this->DrawMarkers();
		return $body;
	}

	/**
	 * Checks that the data produces a 2-D plot
	 */
	protected function CheckValues(&$values)
	{
		parent::CheckValues($values);
		foreach($values[0] as $key => $v)
			if(is_numeric($key) && $key > 0)
				return;

		throw new Exception('No valid data keys for scatter graph');
	}

	/**
	 * The position is the key
	 */
	protected function GetKey($pos)
	{
		return $pos;
	}

}

