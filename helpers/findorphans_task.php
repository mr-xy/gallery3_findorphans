<?php defined("SYSPATH") or die("No direct script access.");
/**
* Gallery - a web based photo album viewer and editor
* Copyright (C) 2000-2011 Bharat Mediratta
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or (at
* your option) any later version.
*
* This program is distributed in the hope that it will be useful, but
* WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
* General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program; if not, write to the Free Software
* Foundation, Inc., 51 Franklin Street - Fifth Floor, Boston, MA  02110-1301, USA.
*/

error_reporting(E_CORE_ERROR);

error_reporting(E_ALL);
ini_set("display_errors", 1);

class findorphans_task_Core {
    static function available_tasks() {

        list ($remaining, $total, $percent) = findorphans::stats();
        return array(Task_Definition::factory()
                    //->callback("findorphans_task::update_index")
                    ->name(t("Find Orphans"))
                    ->description($remaining
                    ? t2("1 album needs to be scanned",
                    "%count (%percent%) of your items need to be checked on server. See list above.",
                    $remaining, array("percent" => (100 - $percent)))
                    : t("All albums in database have corresponding folder on server"))
                    ->severity($remaining ? log::WARNING : log::SUCCESS));
    }

    static function update_index($task) {
        try {
            $completed = $task->get("completed", 0);

            $start = microtime(true);
            
            foreach (ORM::factory("item")
                //->where("relative_path_cache", "IS", null)
                //->where("id", "<>", 1)
                //->limit(10000)
                ->find_all() as $item) {
                // The query above can take a long time, so start the timer after its done
                // to give ourselves a little time to actually process rows.
                if (!isset($start)) {
                    $start = microtime(true);
                }
                
                $item->relative_path();
                $completed++;

                if (microtime(true) - $start > .75) {
                    break;
                }
            }

            list ($remaining, $total, $percent) = findorphans::stats();
            $task->set("completed", $completed);
            if ($remaining == 0 || !($remaining + $completed)) {
                $task->done = true;
                $task->state = "success";
                site_status::clear("find_orphans");
                $task->percent_complete = 100;
            } else {
                $task->percent_complete = round(100 * $completed / ($remaining + $completed));
            }
            $task->status = t2("one record updated, index is %percent% up-to-date",
            "%count records updated, index is %percent% up-to-date",
            $completed, array("percent" => $percent));
        } catch (Exception $e) {
            $task->done = true;
            $task->state = "error";
            $task->status = $e->getMessage();
            $task->log((string)$e);
        }
    }
}
