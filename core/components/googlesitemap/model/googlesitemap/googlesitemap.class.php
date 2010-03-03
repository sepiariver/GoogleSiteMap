<?php
/**
 * GoogleSiteMap
 *
 * Copyright 2009 by Shaun McCormick <shaun@collabpad.com>
 *
 * - Based on Michal Till's MODx Evolution GoogleSiteMap_XML snippet
 *
 * GoogleSiteMap is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option) any
 * later version.
 *
 * GoogleSiteMap is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * GoogleSiteMap; if not, write to the Free Software Foundation, Inc., 59 Temple
 * Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @package googlesitemap
 */
/**
 * @package googlesitemap
 */
class GoogleSiteMap {
    /**
     * Creates an instance of the GoogleSiteMap class.
     */
    function __construct(modX &$modx,array $config = array()) {
        $this->modx =& $modx;
        $this->config = array_merge(array(
            'allowedtemplates' => '',
            'context' => '',
            'googleSchema' => 'http://www.google.com/schemas/sitemap/0.84',
            'hideDeleted' => true,
            'published' => true,
            'searchable' => true,
            'sortBy' => 'menuindex',
            'sortByAlias' => 'modResource',
            'sortDir' => 'ASC',
            'templateFilter' => 'id',
        ),$config);
    }

    /**
     * Runs the sitemap generation.
     *
     * @access public
     * @return string The XML google output
     */
    public function generate() {
        $xml = "<urlset xmlns=\"".$this->config['googleSchema']."\">\n";
        $xml .= $this->_run(0);
        $xml .= "</urlset>";
        return $xml;
    }

    /**
     * Run the Google SiteMap XML generation, recursively
     *
     * @access private
     * @param integer $currentParent The current parent resource the iteration
     * is on
     * @param integer $selfId If specified, will exclude this ID
     * @return string The generated XML
     */
    private function _run($currentParent = 0,$selfId = -1) {
        $output = '';

        $c = $this->modx->newQuery('modResource');
        $c->leftJoin('modResource','Children');
        $c->select('modResource.*, COUNT(Children.id) AS children');
        $c->where(array(
            'parent' => $currentParent,
        ));
        if (!empty($this->config['context'])) {
            $c->where(array('context_key' => $this->config['context']));
        } else {
            $c->where(array('context_key' => $this->modx->context->get('key')));
        }
        if ($this->config['published']) {
            $c->where(array('published' => true));
        }
        if ($this->config['hideDeleted']) {
            $c->where(array('deleted' => false));
        }
        if ($this->config['searchable']) {
            $c->where(array('searchable' => true));
        }

        if (!empty($this->config['allowedtemplates'])) {
            $atpls = split(',',$this->config['allowedtemplates']);
            array_walk($atpls,'quoteArrayItem');
            $tpls = implode(',',$atpls);

            $c->innerJoin('modTemplate','Template');
            $c->where(array(
                'Template.'.$this->config['templateFilter'].' IN ('.$tpls.')',
            ));
        }

        $c->sortby($this->config['sortBy'],$this->config['sortDir']);
        $c->groupby('modResource.id');
        $children = $this->modx->getCollection('modResource',$c);

        foreach ($children as $child) {
            $id = $child->get('id');
            if ($selfId == $id) continue;

            $url = $this->modx->makeUrl($id);
            $url = $this->modx->getOption('site_url').ltrim($url,'/');

            $date = $child->get('editedon') ? $child->get('editedon') : $child->get('createdon');
            $date = date("Y-m-d", strtotime($date));
            /* Get the date difference */
            $datediff = datediff("d", $date, date("Y-m-d"));
            if ($datediff <=1) {
                $priority = '1.0';
                $update = 'daily';
            } elseif (($datediff >1) && ($datediff<=7)) {
                $priority = '0.75';
                $update = 'weekly';
            } elseif (($datediff >7) && ($datediff<=30)) {
                $priority = '0.50';
                $update = 'weekly';
            } else {
                $priority = '0.25';
                $update = 'monthly';
            }
            $output .= "<url>\n";
            $output .= "<loc>$url</loc>\n";
            $output .= "<lastmod>$date</lastmod>\n";
            $output .= "<changefreq>$update</changefreq>\n";
            $output .= "<priority>$priority</priority>\n";
            $output .= "</url>\n";
            if ($child->get('children') > 0) {
                $output .= $this->_run($child->get('id'),$selfId);
            }
        }
        return $output;
    }
}



if (!function_exists('quoteArrayItem')) {
    function quoteArrayItem(&$v) { $v = '"'.$v.'"'; }
}
if (!function_exists('datediff')) {
    /**
     * @param string $interval Can be:
        yyyy - Number of full years
        q - Number of full quarters
        m - Number of full months
        y - Difference between day numbers
        (eg 1st Jan 2004 is "1", the first day. 2nd Feb 2003 is "33". The
        datediff is "-32".)
        d - Number of full days
        w - Number of full weekdays
        ww - Number of full weeks
        h - Number of full hours
        n - Number of full minutes
        s   - Number of full seconds (default)
     * @param $datefrom
     * @param $dateto
     * @param $using_timestamps
     * @return
     * @package googlesitemap
     */
    function datediff($interval, $datefrom, $dateto, $using_timestamps = false) {
        if (!$using_timestamps) {
            $datefrom = strtotime($datefrom, 0);
            $dateto = strtotime($dateto, 0);
        }
        $difference = $dateto - $datefrom; /* Difference in seconds */
        switch($interval) {
            case 'yyyy': /* Number of full years */
                $years_difference = floor($difference / 31536000);
                if (mktime(date("H", $datefrom), date("i", $datefrom), date("s", $datefrom), date("n", $datefrom), date("j", $datefrom), date("Y", $datefrom)+$years_difference) > $dateto) {
                    $years_difference--;
                }
                if (mktime(date("H", $dateto), date("i", $dateto), date("s", $dateto), date("n", $dateto), date("j", $dateto), date("Y", $dateto)-($years_difference+1)) > $datefrom) {
                    $years_difference++;
                }
                $datediff = $years_difference;
                break;

            case 'q': /* Number of full quarters */
                $quarters_difference = floor($difference / 8035200);
                while (mktime(date("H", $datefrom), date("i", $datefrom), date("s", $datefrom), date("n", $datefrom)+($quarters_difference*3), date("j", $dateto), date("Y", $datefrom)) < $dateto) {
                    $quarters_difference++;
                }
                $quarters_difference--;
                $datediff = $quarters_difference;
                break;

            case 'm': /* Number of full months */
                $months_difference = floor($difference / 2678400);
                while (mktime(date("H", $datefrom), date("i", $datefrom), date("s", $datefrom), date("n", $datefrom)+($months_difference), date("j", $dateto), date("Y", $datefrom)) < $dateto) {
                    $months_difference++;
                }
                $months_difference--;
                $datediff = $months_difference;
                break;

            case 'y': /* Difference between day numbers */
                $datediff = date('z',$dateto) - date('z',$datefrom);
                break;

            case 'd': /* Number of full days */
                $datediff = floor($difference / 86400);
                break;

            case 'w': /* Number of full weekdays */
                $days_difference = floor($difference / 86400);
                $weeks_difference = floor($days_difference / 7); /* Complete weeks */
                $first_day = date('w', $datefrom);
                $days_remainder = floor($days_difference % 7);
                /* Do we have a Saturday or Sunday in the remainder? */
                $odd_days = $first_day + $days_remainder;
                if ($odd_days > 7) { /* Sunday */
                    $days_remainder--;
                }
                if ($odd_days > 6) { /* Saturday */
                    $days_remainder--;
                }
                $datediff = ($weeks_difference * 5) + $days_remainder;
                break;

            case 'ww': /* Number of full weeks */
                $datediff = floor($difference / 604800);
                break;

            case 'h': /* Number of full hours */
                $datediff = floor($difference / 3600);
                break;

            case 'n': /* Number of full minutes */
                $datediff = floor($difference / 60);
                break;

            default: /* Number of full seconds (default) */
                $datediff = $difference;
                break;
        }
        return $datediff;
    }
}