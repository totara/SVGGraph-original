<?php
/**
 * Copyright (C) 2011 Graham Breach
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
require_once('SVGGraphMultiGraph.php');

/**
 * MultiLineGraph - joined line, with axes and grid
 */
class MultiLineGraph extends PointGraph {

	protected $line_stroke_width = 2;
	protected $line_dash = null;
	protected $fill_under = false;
	protected $fill_opacity = 0.5;
	protected $multi_graph;

	public function Draw()
	{
		$assoc = $this->AssociativeKeys();
		$this->CalcAxes($assoc);
		$body = $this->Grid();

		$plots = '';

		$ccount = count($this->colours);
		$chunk_count = count($this->values);
		for($i = 0; $i < $chunk_count; ++$i) {
			$bnum = 0;
			$cmd = 'M';
			$path = '';
			$attr = array('fill' => 'none');
			$fill = $this->multi_graph->Option($this->fill_under, $i);
			$dash = $this->multi_graph->Option($this->line_dash, $i);
			$stroke_width = $this->multi_graph->Option($this->line_stroke_width, $i);
			if($fill) {
				$cmd = 'L';
				$attr['fill'] = $this->GetColour($i % $ccount);
				$attr['fill-opacity'] = $this->multi_graph->Option($this->fill_opacity, $i);
			}
			if(!is_null($dash))
				$attr['stroke-dasharray'] = $dash;
			$attr['stroke-width'] = $stroke_width <= 0 ? 1 : $stroke_width;


			foreach($this->multi_graph->all_keys as $key) {
				$value = $this->multi_graph->GetValue($key, $i);
				$point_pos = $this->GridPosition($key, $bnum);
				if(!is_null($value) && !is_null($point_pos)) {
					$x = $point_pos;
					$y = $this->height - $this->pad_bottom - $this->y0 - ($value * $this->bar_unit_height);

					if($fill && $path == '')
						$path = 'M' . $x . ' ' . ($this->height - $this->pad_bottom - $this->y0);
					$path .= "$cmd$x $y ";

					// no need to repeat same L command
					$cmd = $cmd == 'M' ? 'L' : '';
					$this->AddMarker($x, $y, $key, $value, NULL, $i);
				}
				++$bnum;
			}

			if($fill)
				$path .= $cmd . $x . ' ' . ($this->height - $this->pad_bottom - $this->y0) . 'z';

			$attr['d'] = $path;
			$attr['stroke'] = $this->GetColour($i % $ccount, true);
			$plots .= $this->Element('path', $attr);
		}

		$group = array();
		$this->ClipGrid($group);
		$body .= $this->Element('g', $group, NULL, $plots);
		$body .= $this->Axes();
		$body .= $this->CrossHairs();
		$body .= $this->DrawMarkers();
		return $body;
	}

	protected function CheckValues(&$values)
	{
		parent::CheckValues($values);

		if($this->GetHorizontalCount() < 2)
			throw new Exception('Not enough values for line graph');
	}

	/**
	 * construct multigraph
	 */
	public function Values($values)
	{
		parent::Values($values);
		$this->multi_graph = new MultiGraph($this->values);
	}

	/**
	 * The horizontal count is reduced by one
	 */
	protected function GetHorizontalCount()
	{
		return $this->multi_graph->KeyCount();
	}

	/**
	 * Returns the maximum value
	 */
	protected function GetMaxValue()
	{
		return $this->multi_graph->GetMaxValue();
	}

	/**
	 * Returns the minimum value
	 */
	protected function GetMinValue()
	{
		return $this->multi_graph->GetMinValue();
	}

	/**
	 * Returns the key from the MultiGraph
	 */
	protected function GetKey($index)
	{
		return $this->multi_graph->GetKey($index);
	}

	protected function GetMaxKey()
	{
		return $this->multi_graph->GetMaxKey();
	}

	protected function GetMinKey()
	{
		return $this->multi_graph->GetMinKey();
	}

}

