<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * File area helpers for future module content operations.
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_espace\helper;

defined('MOODLE_INTERNAL') || die();

use context;
use file_storage;
use moodle_exception;
use stored_file;

/**
 * Thin wrapper around Moodle file API for future module services.
 */
final class FileHelper {

    /**
     * @var file_storage
     */
    private file_storage $fs;

    public function __construct(?file_storage $fs = null) {
        $this->fs = $fs ?? get_file_storage();
    }

    /**
     * Get a draft item id for the current user.
     *
     * @return int
     */
    public function create_draft_itemid(): int {
        return file_get_unused_draft_itemid();
    }

    /**
     * Copy files from a user draft area into a permanent file area.
     *
     * @param int $draftitemid
     * @param context $context
     * @param string $component
     * @param string $filearea
     * @param int $itemid
     */
    public function save_draft_area(
        int $draftitemid,
        context $context,
        string $component,
        string $filearea,
        int $itemid
    ): void {
        file_save_draft_area_files(
            $draftitemid,
            $context->id,
            $component,
            $filearea,
            $itemid,
            ['subdirs' => true]
        );
    }

    /**
     * Delete all files in a file area.
     *
     * @param context $context
     * @param string $component
     * @param string $filearea
     * @param int $itemid
     */
    public function delete_area_files(
        context $context,
        string $component,
        string $filearea,
        int $itemid
    ): void {
        $this->fs->delete_area_files($context->id, $component, $filearea, $itemid);
    }

    /**
     * Fetch a stored file or throw.
     *
     * @param context $context
     * @param string $component
     * @param string $filearea
     * @param int $itemid
     * @param string $filepath
     * @param string $filename
     * @return stored_file
     * @throws moodle_exception
     */
    public function get_file(
        context $context,
        string $component,
        string $filearea,
        int $itemid,
        string $filepath,
        string $filename
    ): stored_file {
        $file = $this->fs->get_file(
            $context->id,
            $component,
            $filearea,
            $itemid,
            $filepath,
            $filename
        );

        if (!$file) {
            throw new moodle_exception('errorfilenotfound', 'local_espace', '', $filename);
        }

        return $file;
    }
}
