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

require_once('SVGGraphGridGraph.php');

abstract class ThreeDGraph extends GridGraph {

	protected $project_angle = 30;
	protected $label_centre = true;

	// Number of data ranges
	private $depth = 1;

	private $depth_unit = 1;


	/**
	 * Returns the projection angle in radians
	 */
	protected function AngleRadians()
	{
		return deg2rad($this->project_angle);
	}

	/**
	 * Converts x,y,z coordinates into flat x,y
	 */
	protected function Project($x, $y, $z)
	{
		$a = $this->AngleRadians();
		$x1 = $z * cos($a);
		$y1 = $z * sin($a);
		return array($x + $x1, $y - $y1);
	}


	/**
	 * Calculates the sizes of the 3D axes and grid
	 */
	protected function CalcAxes($h_by_count = false, $bar = false)
	{
		// calculate bar 
		$count = $this->GetHorizontalDivision();
		$a = $this->AngleRadians();

		if(!$this->label_adjust_done)
			$this->LabelAdjustment();

		// adjust grid height for depth
		$this->depth_unit = ($this->width - $this->pad_left - $this->pad_right) / ($count + $this->depth * cos($a));
		$this->g_width = $count * $this->depth_unit;
		$this->g_height = ($this->height - $this->pad_top - $this->pad_bottom) - ($this->depth * $this->depth_unit * sin($a));
		parent::CalcAxes($h_by_count, $bar);
	}


	/**
	 * Draws the grid behind the bar / line graph
	 */
	protected function Grid()
	{
		if(!$this->show_grid)
			return '';

		$this->CalcGrid();
		$x_w = $this->axis_width;
		$y_h = $this->axis_height;
		$x1 = $this->pad_left;
		$x2 = $x1 + $x_w + $this->axis_overlap;
		$y1 = $this->height - $this->pad_bottom;
		$y2 = $y1 - $y_h - $this->axis_overlap;
		$h = $this->height - $this->pad_bottom - $this->pad_top;
		$w = $this->width - $this->pad_left - $this->pad_right;

		// move to depth
		$z = $this->depth * $this->depth_unit;
		list($xd,$yd) = $this->Project(0, 0, $z);

		$subpath = $path = '';
		if($this->show_grid_subdivisions) {
			foreach($this->y_subdivs as $y) 
				$subpath .= "M$x1 {$y}l$xd {$yd}l$x_w 0";
			foreach($this->x_subdivs as $x) 
				$subpath .= "M$x {$y1}l$xd {$yd}l0 " . -$y_h;
			if($subpath != '') {
				$opts = array('d' => $subpath, 'stroke' => $this->grid_subdivision_colour, 'fill' => 'none');
				$subpath = $this->Element('path', $opts);
			}
		}

		// start with axis lines
		$path .= "M$x1 {$y1}l$x_w 0M$x1 {$y1}l0 " . -$y_h;
		foreach($this->y_points as $y)
			$path .= "M$x1 {$y}l$xd {$yd}l$x_w 0";
		foreach($this->x_points as $x)
			$path .= "M$x {$y1}l$xd {$yd}l0 " . -$y_h;

		$opts = array('d' => $path, 'stroke' => $this->grid_colour, 'fill' => 'none');
		$path = $this->Element('path', $opts);
		return $subpath . $path;
	}

	/**
	 * clamps a value to the grid boundaries
	 */
	protected function ClampVertical($val)
	{
		return max($this->height - $this->pad_bottom - $this->g_height,
			min($this->height - $this->pad_bottom, $val));
	}

	protected function ClampHorizontal($val)
	{
		return max($this->width - $this->pad_right - $this->g_width,
			min($this->width - $this->pad_right, $val));
	}

	/**
	 * Figure out how many bars there are
	 */
	protected function GetHorizontalDivision()
	{
		if(is_null($this->axis_min_h) && is_null($this->axis_max_h))
			return $this->GetHorizontalCount();
		$start = is_null($this->axis_min_h) ? $this->GetMinKey() : $this->axis_min_h;
		$end = is_null($this->axis_max_h) ? $this->GetMaxKey() : $this->axis_max_h;
		return $end - $start + 1;
	}
}

