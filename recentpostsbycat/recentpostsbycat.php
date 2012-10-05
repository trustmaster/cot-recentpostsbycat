<?php
/* ====================
[BEGIN_COT_EXT]
Hooks=index.tags
Tags=index.tpl:{RECENT_POSTS_*}
[END_COT_EXT]
==================== */

defined('COT_CODE') or die('Wrong URL');

require_once cot_incfile('recentitems', 'plug');
require_once cot_incfile('forums', 'module');

$lines = preg_split('#\r?\n#', $cfg['plugin']['recentpostsbycat']['boxes']);
if (count($lines) > 0)
{
	foreach ($lines as $line)
	{
		$parts = explode(':', $line);
		if (count($parts) == 3)
		{
			$box_name = trim($parts[0]);
			$limit = (int) trim($parts[2]);
			$cats = explode(',', $parts[1]);
			for ($i = 0; $i < count($cats); $i++)
			{
				$cats[$i] = trim($cats[$i]);
			}

			$res = cot_build_recentpostsbycat('recentitems.forums.index', $limit, 0, $cfg['plugin']['recentitems']['recentforumstitle'], $cats);
			$t->assign('RECENT_POSTS_' . mb_strtoupper($box_name), $res);
		}
	}
}

function cot_build_recentpostsbycat($template, $maxperpage = 5, $d = 0, $titlelength = 0, $cats = array())
{
	global $db, $L, $cfg, $db_forum_topics, $theme, $usr, $sys, $R, $structure;
	$recentitems = new XTemplate(cot_tplfile($template, 'plug'));

	$catsub = array();

	foreach ($cats as $cat)
	{
		$catsub = array_merge($catsub, cot_structure_children('forums', $cat));
	}

	$incat = "AND ft_cat IN ('" . implode("','", $catsub) . "')";

	//$sql = $db->query("SELECT * FROM $db_forum_topics WHERE (ft_movedto IS NULL OR ft_movedto = '') AND ft_mode=0 " . $incat . "	ORDER by ft_updated DESC LIMIT $maxperpage");
	$sql = $db->query("SELECT * FROM $db_forum_topics WHERE ft_updated >= 0 " . $incat . " ORDER by ft_updated DESC LIMIT $maxperpage");

	$ft_num = 0;
	while ($row = $sql->fetch())
	{
		$row['ft_icon'] = 'posts';
		$row['ft_postisnew'] = FALSE;
		$row['ft_pages'] = '';
		$ft_num++;
		if ((int)$titlelength > 0 && mb_strlen($row['ft_title']) > $titlelength)
		{
			$row['ft_title'] = cot_string_truncate($row['ft_title'], $titlelength, false). "...";
		}
		$build_forum = cot_breadcrumbs(cot_forums_buildpath($row['ft_cat'], false), false);
		$build_forum_short = cot_rc_link(cot_url('forums', 'm=topics&s=' . $row['ft_cat']), htmlspecialchars($structure['forums'][$row['ft_cat']]['title']));

		if ($row['ft_mode'] == 1)
		{
			$row['ft_title'] = "# " . $row['ft_title'];
		}

		if ($row['ft_movedto'] > 0)
		{
			$row['ft_url'] = cot_url('forums', 'm=posts&q=' . $row['ft_movedto']);
			$row['ft_icon'] = $R['forums_icon_posts_moved'];
			$row['ft_title'] = $L['Moved'] . ": " . $row['ft_title'];
			$row['ft_lastpostername'] = $R['forums_code_post_empty'];
			$row['ft_postcount'] = $R['forums_code_post_empty'];
			$row['ft_replycount'] = $R['forums_code_post_empty'];
			$row['ft_viewcount'] = $R['forums_code_post_empty'];
			$row['ft_lastpostername'] = $R['forums_code_post_empty'];
			$row['ft_lastposturl'] = cot_url('forums', 'm=posts&q=' . $row['ft_movedto'] . '&n=last', '#bottom');
			$row['ft_lastpostlink'] = cot_rc_link($row['ft_lastposturl'], $R['icon_follow']) . ' ' . $L['Moved'];
			$row['ft_timeago'] = cot_build_timegap($row['ft_updated'], $sys['now_offset']);
		}
		else
		{
			$row['ft_url'] = cot_url('forums', 'm=posts&q=' . $row['ft_id']);
			$row['ft_lastposturl'] = ($usr['id'] > 0 && $row['ft_updated'] > $usr['lastvisit']) ?
				cot_url('forums', 'm=posts&q=' . $row['ft_id'] . '&n=unread', '#unread') :
				cot_url('forums', 'm=posts&q=' . $row['ft_id'] . '&n=last', '#bottom');
			$row['ft_lastpostlink'] = ($usr['id'] > 0 && $row['ft_updated'] > $usr['lastvisit']) ?
				cot_rc_link($row['ft_lastposturl'], $R['icon_unread'], 'rel="nofollow"') :
				cot_rc_link($row['ft_lastposturl'], $R['icon_follow'], 'rel="nofollow"');
			$row['ft_lastpostlink'] .= cot_date('datetime_medium', $row['ft_updated']);
			$row['ft_timeago'] = cot_build_timegap($row['ft_updated'], $sys['now_offset']);
			$row['ft_replycount'] = $row['ft_postcount'] - 1;

			if ($row['ft_updated'] > $usr['lastvisit'] && $usr['id'] > 0)
			{
				$row['ft_icon'] .= '_new';
				$row['ft_postisnew'] = TRUE;
			}

			if ($row['ft_postcount'] >= $cfg['forums']['hottopictrigger'] && !$row['ft_state'] && !$row['ft_sticky'])
			{
				$row['ft_icon'] = ($row['ft_postisnew']) ? 'posts_new_hot' : 'posts_hot';
			}
			else
			{
				if ($row['ft_sticky'])
				{
					$row['ft_icon'] .= '_sticky';
				}

				if ($row['ft_state'])
				{
					$row['ft_icon'] .= '_locked';
				}
			}

			$row['ft_icon'] = cot_rc('forums_icon_topic_t', array('icon' => $row['ft_icon'], 'title' => $L['recentitems_' . $row['ft_icon']]));
			$row['ft_lastpostername'] = cot_build_user($row['ft_lastposterid'], htmlspecialchars($row['ft_lastpostername']));
		}

		$row['ft_firstpostername'] = cot_build_user($row['ft_firstposterid'], htmlspecialchars($row['ft_firstpostername']));

		if ($row['ft_postcount'] > $cfg['forums']['maxtopicsperpage'] && $cfg['forums']['maxtopicsperpage'] > 0)
		{
			$row['ft_maxpages'] = ceil($row['ft_postcount'] / $cfg['forums']['maxtopicsperpage']);
			$row['ft_pages'] = $L['Pages'] . ":";
		}

		$recentitems->assign(array(
			'FORUM_ROW_ID' => $row['ft_id'],
			'FORUM_ROW_STATE' => $row['ft_state'],
			'FORUM_ROW_ICON' => $row['ft_icon'],
			'FORUM_ROW_TITLE' => htmlspecialchars($row['ft_title']),
			'FORUM_ROW_PATH' => $build_forum,
			'FORUM_ROW_PATH_SHORT' => $build_forum_short,
			'FORUM_ROW_DESC' => htmlspecialchars($row['ft_desc']),
			'FORUM_ROW_PREVIEW' => $row['ft_preview'] . '...',
			'FORUM_ROW_CREATIONDATE' => cot_date('datetime_short', $row['ft_creationdate']),
			'FORUM_ROW_CREATIONDATE_STAMP' => $row['ft_creationdate'] + $usr['timezone'] * 3600,
			'FORUM_ROW_UPDATED' => $row['ft_lastpostlink'],
			'FORUM_ROW_UPDATED_STAMP' => $row['ft_updated'] + $usr['timezone'] * 3600,
			'FORUM_ROW_TIMEAGO' => $row['ft_timeago'],
			'FORUM_ROW_POSTCOUNT' => $row['ft_postcount'],
			'FORUM_ROW_REPLYCOUNT' => $row['ft_replycount'],
			'FORUM_ROW_VIEWCOUNT' => $row['ft_viewcount'],
			'FORUM_ROW_FIRSTPOSTER' => $row['ft_firstpostername'],
			'FORUM_ROW_LASTPOSTER' => $row['ft_lastpostername'],
			'FORUM_ROW_LASTPOSTURL' => $row['ft_lastposturl'],
			'FORUM_ROW_URL' => $row['ft_url'],
			'FORUM_ROW_PAGES' => $row['ft_pages'],
			'FORUM_ROW_MAXPAGES' => $row['ft_maxpages'],
			'FORUM_ROW_NUM' => $ft_num,
			'FORUM_ROW_ODDEVEN' => cot_build_oddeven($ft_num),
			'FORUM_ROW' => $row
		));

		$recentitems->parse('MAIN.TOPICS_ROW');
	}
	$sql->closeCursor();

	$recentitems->parse('MAIN');
	return $recentitems->text('MAIN');
}
?>
