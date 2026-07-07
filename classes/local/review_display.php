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

namespace mod_confprogram\local;

/**
 * Renders a list of completed confprogram_review rows as trusted, ready-to-echo HTML:
 * one card per review, with the reviewer's identity (respecting blind review, via
 * \mod_confprogram\local\identity) or an anonymised label, the numeric grade, and a
 * best-effort per-criterion rubric breakdown.
 *
 * Factored out of feedback.php (the submitter "my feedback" screen) so view.php's own
 * Review-phase "my reviews" section (one per submission a user speaks on -- see
 * speaker_submissions.php -- reviewed once at least one reviewer has completed it, per
 * rounds::get_current_round()) can reuse the exact same rendering without duplicating
 * the Advanced Grading API plumbing. Both callers fetch their own \stdClass[] of
 * confprogram_review rows their own way (feedback.php: the round that led to a
 * 'resubmit' decision; view.php: the submission's current round) and pass them here.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class review_display {
    /**
     * Renders a submission's completed reviews as trusted HTML.
     *
     * @param \context $context The confprogram course-module context
     * @param \stdClass[] $reviews Completed confprogram_review rows (e.g. from
     *     \mod_confprogram\api::get_reviews_for_round())
     * @return string Trusted HTML -- do not pass through s()/format_string() again
     */
    public static function render(\context $context, array $reviews): string {
        global $CFG;

        if (!$reviews) {
            return \html_writer::tag(
                'p',
                get_string('nofeedbackavailable', 'mod_confprogram'),
                ['class' => 'text-muted']
            );
        }

        require_once($CFG->dirroot . '/grade/grading/lib.php');

        $gradingmanager = get_grading_manager($context, 'mod_confprogram', 'review');
        $gradingmethod = $gradingmanager->get_active_method();
        $controller = $gradingmethod ? $gradingmanager->get_controller($gradingmethod) : null;
        $definition = ($controller && $controller->is_form_available()) ? $controller->get_definition() : null;

        $canviewidentity = identity::can_view_identity($context);

        $out = '';
        $i = 1;
        foreach ($reviews as $review) {
            $out .= \html_writer::start_tag('div', ['class' => 'card mb-3']);
            $out .= \html_writer::start_tag('div', ['class' => 'card-body']);

            if ($canviewidentity) {
                $reviewer = \core_user::get_user($review->reviewerid);
                $reviewerlabel = $reviewer ? fullname($reviewer) : '-';
            } else {
                $reviewerlabel = get_string('anonymousreviewer', 'mod_confprogram', $i);
            }
            $out .= \html_writer::tag('h5', $reviewerlabel);
            $out .= \html_writer::tag('p', get_string('grade', 'mod_confprogram') . ': '
                . ($review->grade !== null ? format_float($review->grade, 2) : '-'));

            // Best-effort per-criterion breakdown for rubric-graded reviews. get_or_create_instance()
            // here always finds the exact existing instance rather than creating a new one: we pass
            // the instance's own id, raterid and itemid straight from the confprogram_review row, and
            // fetch_instance() only returns an instance for an exact (id, raterid, itemid) match (see
            // review.php's block comment on why itemid is the confprogram_review row id, not the
            // submission id). If for any reason the grading area's active method has since changed
            // away from rubric, or the instance record is gone, this quietly falls back to just the
            // numeric grade already printed above.
            $renderedcriteria = false;
            if ($gradingmethod === 'rubric' && $definition && !empty($definition->rubric_criteria) && $review->gradinginstanceid) {
                $instance = $controller->get_or_create_instance(
                    (int) $review->gradinginstanceid,
                    (int) $review->reviewerid,
                    (int) $review->id
                );
                $isexpectedinstance = $instance instanceof \gradingform_rubric_instance
                    && (int) $instance->get_id() === (int) $review->gradinginstanceid;
                if ($isexpectedinstance) {
                    $filling = $instance->get_rubric_filling();
                    if (!empty($filling['criteria'])) {
                        $table = new \html_table();
                        $table->head = [
                            get_string('criterion', 'mod_confprogram'),
                            get_string('level', 'mod_confprogram'),
                            get_string('remark', 'mod_confprogram'),
                        ];
                        $table->attributes['class'] = 'generaltable';
                        foreach ($filling['criteria'] as $criterionid => $criteriondata) {
                            $criteriondef = $definition->rubric_criteria[$criterionid] ?? null;
                            if (!$criteriondef) {
                                continue;
                            }
                            $leveldef = $criteriondef['levels'][$criteriondata['levelid']] ?? null;
                            $table->data[] = [
                                format_string($criteriondef['description']),
                                $leveldef ? format_string($leveldef['definition']) : '-',
                                !empty($criteriondata['remark']) ? format_text($criteriondata['remark'], FORMAT_HTML) : '-',
                            ];
                        }
                        $out .= \html_writer::table($table);
                        $renderedcriteria = true;
                    }
                }
            }

            if (!$renderedcriteria) {
                $out .= \html_writer::tag('p', get_string('nocriteriondetail', 'mod_confprogram'), ['class' => 'text-muted']);
            }

            $out .= \html_writer::end_tag('div');
            $out .= \html_writer::end_tag('div');
            $i++;
        }

        return $out;
    }
}
