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
 * MultiScatterGraph - points with axes and grid
 */
class MultiScatterGraph extends PointGraph {

	protected $scatter_2d = false;
	protected $multi_graph;

	public function Draw()
	{
		$assoc = !$this->scatter_2d && $this->AssociativeKeys();
		$this->CalcAxes($assoc);
		$body = $this->Grid();
		$values = $this->GetValues();

		// a scatter graph without markers is empty!
		if($this->marker_size == 0)
			$this->marker_size = 1;

		$ccount = count($this->colours);
		$chunk_count = count($this->values);
		for($i = 0; $i < $chunk_count; ++$i) {
			$bnum = 0;
			foreach($this->multi_graph->all_keys as $key) {
				$value = $this->multi_graph->GetValue($key, $i);
				if($this->scatter_2d && is_array($value)) {
					$key = $value[0];
					$value = $value[1];
				}
				$point_pos = $this->GridPosition($key, $bnum);
				if(!is_null($value) && !is_null($point_pos)) {
					$x = $point_pos;
					$y = $this->height - $this->pad_bottom - $this->y0 - ($value * $this->bar_unit_height);

					$this->AddMarker($x, $y, $key, $value, NULL, $i);
				}
				++$bnum;
			}
		}

		$body .= $this->Axes();
		$body .= $this->CrossHairs();
		$body .= $this->DrawMarkers();
		return $body;
	}

	/**
	 * Sets up values array
	 */
	public function Values($values)
	{
		if(!$this->scatter_2d) {
			parent::Values($values);
		} else {
			$this->values = array();
			$v = func_get_args();
			if(count($v) == 1)
				$v = $v[0];
			if(is_array($v) && isset($v[0]) && is_array($v[0]) && is_array($v[0][0]))
				$this->values = $v;
			elseif(is_array($v) && isset($v[0]) && is_array($v[0]))
				$this->values[0] = $v;
			else
				throw new Exception('Scatter 2D mode requires array of array(x,y) points');
		}
		$this->multi_graph = new MultiGraph($this->values);
	}

	/**
	 * Checks that the data produces a 2-D plot
	 */
	protected function CheckValues(&$values)
	{
		parent::CheckValues($values);
		foreach($values[0] as $key => $v) {
			if(is_numeric($key) && $key > 0)
				return;
		}

		throw new Exception('No valid data keys for scatter graph');
	}

	/**
	 * Overload GetMaxValue to support scatter_2d data
	 */
	protected function GetMaxValue()
	{
		if(!$this->scatter_2d)
			return $this->multi_graph->GetMaxValue();

		function vmax($m,$e) {
			if(is_null($m))
				return $e[1];
			return $e[1] > $m ? $e[1] : $m;
		}
		$maxima = array();
		$chunk_count = count($this->values);
		for($i = 0; $i < $chunk_count; ++$i)
			$maxima[] = array_reduce($this->values[$i], 'vmax', null);

		return max($maxima);
	}

	/**
	 * Overload GetMinValue to support scatter_2d data
	 */
	protected function GetMinValue()
	{
		if(!$this->scatter_2d)
			return $this->multi_graph->GetMinValue();

		function vmin($m,$e) {
			if(is_null($m))
				return $e[1];
			return $e[1] < $m ? $e[1] : $m;
		}
		$minima = array();
		$chunk_count = count($this->values);
		for($i = 0; $i < $chunk_count; ++$i)
			$minima[] = array_reduce($this->values[$i], 'vmin', null);

		return min($minima);
	}

	/**
	 * Overload GetMaxKey to support scatter_2d data
	 */
	protected function GetMaxKey()
	{
		if(!$this->scatter_2d)
			return $this->multi_graph->GetMaxKey();

		function kmax($m,$e) {
			if(is_null($m))
				return $e[0];
			return $e[0] > $m ? $e[0] : $m;
		}
		$maxima = array();
		$chunk_count = count($this->values);
		for($i = 0; $i < $chunk_count; ++$i)
			$maxima[] = array_reduce($this->values[$i], 'kmax', null);
		return max($maxima);
	}

	/**
	 * Overload GetMinKey to support scatter_2d data
	 */
	protected function GetMinKey()
	{
		if(!$this->scatter_2d)
			return $this->multi_graph->GetMinKey();

		function kmin($m,$e) {
			if(is_null($m))
				return $e[0];
			return $e[0] < $m ? $e[0] : $m;
		}
		$minima = array();
		$chunk_count = count($this->values);
		for($i = 0; $i < $chunk_count; ++$i)
			$minima[] = array_reduce($this->values[$i], 'kmin', null);

		return min($minima);
	}

}

