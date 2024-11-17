<?php
/* Copyright (C) 2022-2024 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    core/tpl/digiquali_answers.tpl.php
 * \ingroup digiquali
 * \brief   Template page for answers lines
 */

/**
 * The following vars must be defined:
 * Global     : $conf, $langs, $user
 * Parameters :
 * Objects    : $answer, $object, $objectLine, $sheet
 * Variable   : $publicInterface
 */

if (is_array($sheet->linkedObjects['digiquali_question']) && !empty($sheet->linkedObjects['digiquali_question'])) {
    foreach ($sheet->linkedObjects['digiquali_question'] as $question) {
        $questionAnswer = '';
        $comment        = '';
        $result         = $objectLine->fetchFromParentWithQuestion($object->id, $question->id);
        if (is_array($result) && !empty($result)) {
            $objectLine = array_shift($result);
            $questionAnswer = $objectLine->answer;
            $comment = $objectLine->comment;
        }
        if (!$user->conf->DIGIQUALI_SHOW_ONLY_QUESTIONS_WITH_NO_ANSWER or empty($questionAnswer)) {
            ?>
            <div class="wpeo-table table-flex table-3 table-id-<?php echo $question->id ?>" data-publicInterface="<?php echo $publicInterface; ?>" data-autoSave="<?php echo getDolGlobalInt('DIGIQUALI_' . dol_strtoupper($object->element) . 'DET_AUTO_SAVE_ACTION'); ?>">
                <div class="table-row">
                    <!-- Contenu et commentaire -->
                    <div class="table-cell table-full">
                        <div class="label"><strong><?php print $question->getNomUrl(1, isset($publicInterface) ? 'nolink' : '', 1, '', -1, 1); ?></strong></div>
                        <div class="description"><?php print $question->description; ?></div>
                        <div class="question-comment-container">
                            <div class="question-ref">
                                <?php
                                if (!empty($objectLine->ref) ) {
                                    print '<span class="question-ref-title">' . $objectLine->ref . '</span> :';
                                } ?>
                            </div>
                            <?php if ($question->type == 'Text') : ?>
                                <div class="<?php echo ($object->status > 0) ? 'style="pointer-events: none"' : '' ?>">
                                    <?php
                                    print '<span>' . $langs->trans('Answer') . ' : </span>';
                                    $object->status > $object::STATUS_DRAFT ? print $questionAnswer :
                                    print '<input' . ($object->status > $object::STATUS_DRAFT ? ' disabled' : '') . ' name="answer' . $question->id . '" id="answer' . $question->id . '"class="question-textarea input-answer ' . ($object->status > 0 ? 'disable' : '') . '" value="' . $questionAnswer . '">'; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($question->enter_comment > 0) : ?>
                                <?php print $langs->trans('Comment') . ' : '; ?>
                            <?php endif; ?>
                            <?php if ($question->enter_comment > 0) : ?>
                                <?php if ($object->status > 0 ) : ?>
                                    <?php print $comment; ?>
                                <?php else : ?>
                                    <?php print '<input class="question-textarea question-comment" name="comment' . $question->id . '" id="comment' . $question->id . '" value="' . $comment .  '" ' . ($object->status == 2 ? 'disabled' : '') . '>'; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- Photo OK KO -->
                    <?php if ($question->show_photo > 0) : ?>
                        <div class="table-cell table-450 cell-photo-check wpeo-table">
                            <?php
                            if (getDolGlobalInt('DIGIQUALI_' . dol_strtoupper($object->element) . '_DISPLAY_MEDIAS')) :
                                print saturne_show_medias_linked('digiquali', $conf->digiquali->multidir_output[$conf->entity] . '/question/'. $question->ref . '/photo_ok', 'small', '', 0, 0, 0, 200, 200, 0, 0, 1, 'question/' . $question->ref . '/photo_ok', $question, 'photo_ok', 0, 0, 0,1, 'photo-ok', 0);
                                print saturne_show_medias_linked('digiquali', $conf->digiquali->multidir_output[$conf->entity] . '/question/'. $question->ref . '/photo_ko', 'small', '', 0, 0, 0, 200, 200, 0, 0, 1, 'question/' . $question->ref . '/photo_ko', $question, 'photo_ko', 0, 0, 0,1, 'photo-ko', 0);
                            endif;
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="table-row <?php echo ($conf->browser->layout != 'classic' ? 'center' : ''); ?>">
                    <!-- Galerie -->
                    <?php if ($question->authorize_answer_photo > 0) : ?>
                        <div class="table-cell table-full linked-medias answer_photo_<?php echo $question->id ?>">
                            <?php if ($object->status == 0 ) : ?>
                                <input hidden multiple class="fast-upload<?php echo getDolGlobalInt('SATURNE_USE_FAST_UPLOAD_IMPROVEMENT') ? '-improvement' : ''; ?>" id="fast-upload-answer-photo<?php echo $question->id ?>" type="file" name="userfile[]" capture="environment" accept="image/*">
                                <input type="hidden" class="question-answer-photo" id="answer_photo_<?php echo $question->id ?>" name="answer_photo_<?php echo $question->id ?>" value=""/>
                                <input type="hidden" class="fast-upload-options" data-from-subtype="answer_photo_<?php echo $question->id ?>" data-from-subdir="answer_photo/<?php echo $question->ref ?>"/>
                                <label for="fast-upload-answer-photo<?php echo $question->id ?>">
                                    <div class="wpeo-button button-square-50">
                                        <i class="fas fa-camera"></i><i class="fas fa-plus-circle button-add"></i>
                                    </div>
                                </label>
                                <div class="wpeo-button button-square-50 open-media-gallery add-media modal-open" value="<?php echo $question->id ?>">
                                    <input type="hidden" class="modal-options" data-modal-to-open="media_gallery" data-from-id="<?php echo $object->id ?>" data-from-type="<?php echo $object->element ?>" data-from-subtype="answer_photo_<?php echo $question->id ?>" data-from-subdir="answer_photo/<?php echo $question->ref ?>"/>
                                    <i class="fas fa-folder-open"></i><i class="fas fa-plus-circle button-add"></i>
                                </div>
                            <?php endif; ?>
                            <?php print saturne_show_medias_linked('digiquali', $conf->digiquali->multidir_output[$conf->entity] . '/' . $object->element . '/' . $object->ref . '/answer_photo/' . $question->ref, 'small', '', 0, 0, 0, 50, 50, 0, 0, 1, $object->element . '/' . $object->ref . '/answer_photo/' . $question->ref, $question, '', 0, $object->status == 0, 1); ?>
                        </div>
                    <?php endif; ?>
                    <?php $pictosArray = get_answer_pictos_array(); ?>
                    <?php if ($question->type == 'MultipleChoices') :
                        $answerList = $answer->fetchAll('ASC', 'position', 0, 0,  ['customsql' => 't.status > ' . Answer::STATUS_DELETED . ' AND t.fk_question = ' . $question->id]); ?>
                        <div class="table-cell table-end select-answer answer-cell" <?php echo ($object->status > 0) ? ' style="pointer-events: none"' : '' ?> data-questionId="<?php echo $question->id; ?>">
                            <?php
                            if (preg_match('/,/', $questionAnswer)) {
                                $questionAnswers = preg_split('/,/', $questionAnswer);
                            } else {
                                $questionAnswers = [$questionAnswer];
                            }

                            print '<input type="hidden" class="question-answer" name="answer' . $question->id . '" id="answer' . $question->id . '" value="0">';
                            if (is_array($answerList) && !empty($answerList)) {
                                foreach($answerList as $answerLinked) {
                                    print '<input type="hidden" class="answer-color answer-color-' . $answerLinked->position . '" value="' . $answerLinked->color . '">';
                                    print '<span style="' . (in_array($answerLinked->position, $questionAnswers) ? 'background:' . $answerLinked->color .'; ' : '') . 'color:' . $answerLinked->color . ';" class="answer multiple-answers square ' . ($object->status > 0 ? 'disable' : '') . ' ' . (in_array($answerLinked->position, $questionAnswers) ? 'active' : '') . '" value="' . $answerLinked->position . '">';
                                    if (!empty($answerLinked->pictogram)) {
                                        print $pictosArray[$answerLinked->pictogram]['picto_source'];
                                    } else {
                                        print $answerLinked->value;
                                    }
                                    print '</span>';
                                }
                            }
                            ?>
                        </div>
                    
                    <?php elseif ($question->type == 'Percentage') : ?>
                        <div class="table-cell table-end answer-cell table-flex <?php echo ($object->status > 0) ? 'style="pointer-events: none"' : '' ?>" data-questionId="<?php echo $question->id; ?>">
                            <?php
                            print '<span class="table-cell" value="">';
                            print $langs->transnoentities('Answer') . ' : ';
                            print '</span>';
                            print '<span class="table-cell" value="">';
                            print '<input' . ($object->status > $object::STATUS_DRAFT ? ' disabled' : '') . ' name="answer' . $question->id . '" id="answer' . $question->id . '" type="number" min="0" max="100" class="input-answer ' . ($object->status > 0 ? 'disable' : '') . ' ' . ($questionAnswer == $answerLinked->position ? 'active' : '') . '" value="' . $questionAnswer . '"> %';
                            print '</span>';
                            ?>
                        </div>
                    <?php elseif ($question->type == 'Range') : ?>
                        <div class="table-cell table-end answer-cell table-flex <?php echo ($object->status > 0) ? 'style="pointer-events: none"' : '' ?>" data-questionId="<?php echo $question->id; ?>">
                            <?php
                            print '<span class="table-cell" value="">';
                            print $langs->transnoentities('Answer') . ' : ';
                            print '</span>';
                            print '<span class="table-cell" value="">';
                            print '<input '. ($object->status > $object::STATUS_DRAFT ? 'disabled' : '') .' name="answer' . $question->id . '" id="answer' . $question->id . '" type="number" class="input-answer ' . ($object->status > 0 ? 'disable' : '') . ' ' . ($questionAnswer == $answerLinked->position ? 'active' : '') . '" value="' . $questionAnswer . '">';
                            print '</span>';
                            ?>
                        </div>
		    <?php else://if ($question->type == 'UniqueChoice' || $question->type == 'OkKo' || $question->type == 'OkKoToFixNonApplicable') :
                        $answerList = $answer->fetchAll('ASC', 'position', 0, 0, ['customsql' => 't.status > ' . Answer::STATUS_DELETED . ' AND t.fk_question = ' . $question->id]); ?>
                        <div class="table-cell table-end select-answer answer-cell table-300" <?php echo ($object->status > 0) ? 'style="pointer-events: none"' : '' ?> data-questionId="<?php echo $question->id; ?>">
                            <?php
                            print '<input type="hidden" class="question-answer" name="answer' . $question->id . '" id="answer' . $question->id . '" value="0">';
                            if (is_array($answerList) && !empty($answerList)) {
                                foreach($answerList as $answerLinked) {
                                    print '<input type="hidden" class="answer-color answer-color-' . $answerLinked->position . '" value="' . $answerLinked->color . '">';
                                    print '<span style="' . ($questionAnswer == $answerLinked->position ? 'background:' . $answerLinked->color . '; ' : '') . 'color:' . $answerLinked->color . ';" class="answer ' . ($object->status > 0 ? 'disable' : '') . ' ' . ($questionAnswer == $answerLinked->position ? 'active' : '') . '" value="' . $answerLinked->position . '">';
                                    if (!empty($answerLinked->pictogram)) {
                                        print $pictosArray[$answerLinked->pictogram]['picto_source'];
                                    } else {
                                        print $answerLinked->value;
                                    }
                                    print '</span>';
                                }
                            } ?>
                        </div>
                    <?php endif; ?>
		    
                </div>
            </div>
            <?php
        }
    }
}
