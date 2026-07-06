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
 * Language strings for mod_confprogram (Japanese).
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['acceptedsubmissions'] = '採択された応募';
$string['alldays'] = 'すべての日程';
$string['anonymousreviewer'] = '査読者{$a}';
$string['assignreviewer'] = '査読者を割り当て';
$string['assignreviewers'] = '査読者を割り当て';
$string['backtoall'] = 'すべての応募に戻る';
$string['blindreview'] = '匿名査読';
$string['blindreview_help'] = '有効にすると、「実名を表示する」権限を持つユーザーを除き、査読の流れ全体を通じて応募者と査読者の身元がお互いに非表示になります。これは画面表示にのみ影響し、保存されるデータを制限するものではありません。';
$string['bulkassigngroup'] = '選択した応募に査読者グループを割り当て';
$string['clearfilter'] = '絞り込みを解除';
$string['completedreviews'] = '完了した査読';
$string['confprogram:addinstance'] = 'Conference Program アクティビティを新規追加する';
$string['confprogram:decide'] = '採択・却下・再提出・補欠の判定を行う';
$string['confprogram:favourite'] = '応募をお気に入りに登録する';
$string['confprogram:managenotifications'] = '判定通知テンプレートを管理する';
$string['confprogram:managereviewers'] = '査読者の割り当てと査読設定を管理する';
$string['confprogram:manageunvetted'] = '応募の査読対象外フラグを設定・解除する';
$string['confprogram:review'] = '割り当てられた応募の査読を行う';
$string['confprogram:viewidentity'] = '匿名査読が有効な場合でも応募者・査読者の実名を閲覧する';
$string['confprogram:viewprogram'] = 'プログラムを閲覧する';
$string['confsubmissionscmid'] = 'Conference Submissions アクティビティ';
$string['confsubmissionscmid_help'] = 'この Conference Program インスタンスが査読・公開対象とする、このコース内の Conference Submissions アクティビティです。';
$string['criterion'] = '評価項目';
$string['currentphase'] = 'このプログラムは現在「{$a}」フェーズです。';
$string['currentreviewers'] = '割り当て済みの査読者';
$string['day'] = '日程';
$string['decision_accept'] = '採択';
$string['decision_reject'] = '却下';
$string['decision_resubmit'] = '再提出';
$string['decision_waitlist'] = '補欠';
$string['decisionreport'] = '判定レポート';
$string['decisionsaved'] = '判定を保存しました。';
$string['defaultmaxreviews'] = '査読者1人あたりの査読数上限（デフォルト）';
$string['defaultmaxreviews_help'] = 'このインスタンスにおいて、1人の査読者が1ラウンドで完了できる査読数の上限です。0 は無制限を意味します。個々の査読者については confprogram_reviewermax レコードで上書きできます。';
$string['deletedfield'] = '（削除された項目）';
$string['displaysettings'] = '表示項目の設定';
$string['displaysettingssaved'] = '表示設定を保存しました。';
$string['editmysubmission'] = '自分の応募を編集';
$string['editreview'] = '査読を編集';
$string['error:invalidconfsubmissionscmid'] = 'このコース内の Conference Submissions アクティビティを選択してください。';
$string['error:invalidnumber'] = '0以上の整数を入力してください。';
$string['error:noconfsubmissions'] = 'このコースにはまだ Conference Submissions アクティビティがありません。先に追加してください。';
$string['error:noreviewform'] = 'このインスタンスにはまだ査読フォームが設定されていません。';
$string['error:notassigned'] = 'あなたはこの応募の査読担当に割り当てられていません。';
$string['error:notowner'] = 'あなたはこの応募の所有者ではありません。';
$string['error:reviewcapreached'] = 'このラウンドで査読できる件数の上限に達しています。';
$string['error:submissionnotavailable'] = 'この応募は現在利用できません。';
$string['error:unvetted'] = 'この応募は査読対象外としてフラグが設定されており、査読の対象外です。';
$string['favourite'] = 'お気に入り';
$string['favouritesonly'] = 'お気に入りのみ';
$string['field'] = '項目';
$string['filteredbytrack'] = 'トラックで絞り込み中: {$a}';
$string['focusedsubmission'] = '再割り当てのため、再提出された下記の応募のみを表示しています。';
$string['grade'] = '評点';
$string['gradeitem:review'] = 'レビュー';
$string['groupreviewmode'] = 'グループ査読モード';
$string['groupreviewmode_help'] = '有効にすると、個々の査読者の代わりに（または個々の査読者に加えて）査読者グループ（通常のコースグループ）に応募を割り当てられるようになります。割り当てられたグループのメンバーであれば誰でも査読を完了できます。';
$string['identityhidden'] = '匿名査読が有効なため、応募者の身元は非表示になっています。';
$string['lastdecision'] = '直近の判定: {$a->decision}（第{$a->round}ラウンド）';
$string['level'] = 'レベル';
$string['makedecision'] = '判定する';
$string['managenotifications'] = '通知の管理';
$string['managereviewform'] = '査読フォームを管理';
$string['markunvetted'] = '査読対象外としてマーク';
$string['messageprovider:submissiondecision'] = 'あなたが発表者に含まれる応募について判定が行われたとき';
$string['modulename'] = 'Conference Program';
$string['modulename_help'] = 'Conference Program アクティビティは、Conference Submissions アクティビティからの応募を査読者による審査フロー（Review phase）にかけ、その後、採択された応募を公開表示します（Display phase）。';
$string['modulenameplural'] = 'Conference Program';
$string['myfeedback'] = 'フィードバック対象: {$a}';
$string['myreviewqueue'] = '自分の査読キュー';
$string['noacceptedsubmissions'] = 'まだ表示できる採択済みの応募はありません。';
$string['nocriteriondetail'] = 'この査読には項目ごとの詳細な内訳はありません。上記の評点をご覧ください。';
$string['nofeedbackavailable'] = '現時点でこの応募に対するフィードバックはありません。';
$string['noinstances'] = 'このコースにはまだ Conference Program アクティビティがありません。';
$string['noreviewersassigned'] = 'まだ査読者が割り当てられていません。';
$string['noreviewscompleted'] = 'まだ査読を完了していません。';
$string['noreviewspending'] = '未完了の査読はありません。';
$string['noreviewsyet'] = 'このラウンドではまだ完了した査読がありません。';
$string['notifbody'] = '本文';
$string['notifbody_help'] = '通知メールの本文です。Moodle 自体の通知システム（既定でメール送信も行われます）を通じて、判定が公開されたとき（Review phase 中に行われた判定は Display phase に切り替わるまで保留されます）に、各発表者へ送信されます。[[fullname]]、[[submissiontitle]]、[[coursename]]、[[decision]]（採択・却下・補欠）を使用できます。';
$string['notifdefaultbody:decision'] = '<p>[[fullname]] 様</p><p>[[coursename]] における、あなたの応募「[[submissiontitle]]」について判定が行われました: <strong>[[decision]]</strong>。</p>';
$string['notifdefaultsubject:decision'] = '応募の判定結果: [[submissiontitle]]';
$string['notificationsenabled'] = '通知を有効にする';
$string['notificationsenabled_help'] = 'このアクティビティのマスタースイッチです。チェックを外すと、下記のテンプレート設定にかかわらず、このインスタンスから判定通知が一切送信されなくなります。';
$string['notifplaceholders'] = '利用可能なプレースホルダー: {$a}。';
$string['notifsubject'] = '件名';
$string['notifsubject_help'] = '通知メールの件名です。下の本文と同じプレースホルダーが使用できます。';
$string['notiftemplatesaved'] = '通知テンプレートを保存しました。';
$string['notinreviewphase'] = 'このインスタンスは Display phase のため、Review phase の画面は利用できません。';
$string['notyetscheduled'] = 'まだスケジュールされていません';
$string['pendingreviews'] = '未完了の査読';
$string['phase'] = 'フェーズ';
$string['phase_display'] = 'Display（公開）';
$string['phase_review'] = 'Review（査読）';
$string['pluginadministration'] = 'Conference Program の管理';
$string['pluginname'] = 'Conference Program';
$string['privacy:metadata:confprogram_assignment'] = '応募の査読を担当するよう割り当てられた査読者。';
$string['privacy:metadata:confprogram_assignment:reviewerid'] = '査読者として割り当てられたユーザーのID。';
$string['privacy:metadata:confprogram_assignment:timecreated'] = '割り当てが作成された日時。';
$string['privacy:metadata:confprogram_decision'] = '応募に対して行われた採択・却下・再提出・補欠の判定。';
$string['privacy:metadata:confprogram_decision:decidedby'] = '判定を行ったユーザーのID。';
$string['privacy:metadata:confprogram_decision:decision'] = '行われた判定内容（採択・却下・再提出・補欠）。';
$string['privacy:metadata:confprogram_decision:round'] = 'この判定が属する査読ラウンド。';
$string['privacy:metadata:confprogram_decision:timecreated'] = '判定が行われた日時。';
$string['privacy:metadata:confprogram_favourite'] = 'Display phase 中にユーザーがお気に入りに登録した応募。';
$string['privacy:metadata:confprogram_favourite:timecreated'] = '応募がお気に入りに登録された日時。';
$string['privacy:metadata:confprogram_favourite:userid'] = '応募をお気に入りに登録したユーザーのID。';
$string['privacy:metadata:confprogram_reviewermax'] = 'グループ査読モードで使用される、査読者ごとの査読数上限の上書き設定。';
$string['privacy:metadata:confprogram_reviewermax:maxreviews'] = 'この査読者に割り当て可能な査読数の上限。';
$string['privacy:metadata:confprogram_reviewermax:userid'] = 'この上書き設定が適用される査読者のID。';
$string['privacy:metadata:confprogram_unvetted'] = '査読対象外としてフラグが設定された応募（招待講演やパネルなど）。';
$string['privacy:metadata:confprogram_unvetted:setby'] = '応募を査読対象外としてフラグを設定したユーザーのID。';
$string['privacy:metadata:confprogram_unvetted:timecreated'] = '応募が査読対象外としてフラグを設定された日時。';
$string['remark'] = '所見';
$string['removereviews'] = 'すべての審査・判定・お気に入りを削除（審査フェーズに戻す）';
$string['resubmissionsneeded'] = '再提出待ちの自分の応募';
$string['review'] = '査読';
$string['reviewer'] = '査読者';
$string['reviewergroup'] = 'グループ: {$a}';
$string['reviewsaved'] = '査読を保存しました。';
$string['reviewsettings'] = '査読設定';
$string['round'] = 'ラウンド';
$string['savedecision'] = '判定を保存';
$string['selectgroup'] = '査読者グループを選択...';
$string['selectreviewer'] = '査読者を選択...';
$string['setupreviewform'] = '査読フォームを設定する';
$string['showallsubmissions'] = 'すべての応募を表示';
$string['showday'] = '表示';
$string['showinlist'] = '一覧に表示';
$string['showinmodal'] = 'モーダルに表示';
$string['startnewreviewround'] = '新しい査読ラウンドを開始';
$string['startreview'] = '査読を開始';
$string['submitreview'] = '査読を提出';
$string['switchtophase'] = '{$a}フェーズに切り替え';
$string['timeandroom'] = '時間 / 会場';
$string['unfavourite'] = 'お気に入りを解除';
$string['unmarkunvetted'] = '査読対象外フラグを解除';
$string['unscheduled'] = '未スケジュール';
$string['unvetted'] = '査読対象外';
$string['unvettedsubmissions'] = '査読対象外の応募';
$string['unvettedsubmissions_help'] = '応募を査読対象外としてフラグを立てると、その応募は査読フローの対象から完全に除外されます（招待基調講演やプログラムに直接追加されたパネルなど）。査読対象外の応募は割り当て画面や査読者のキューには表示されません。';
$string['warningreviewercapreached'] = 'この査読者は、このラウンドの査読数の上限に達しました（またはすでに超過しています）。';
