<?php
/**
 * @file plugins/generic/tjArticleStats/TjArticleStatsPlugin.inc.php
 *
 * Shows "Abstract views" and "Downloads" for each article:
 *  - below each article in issue tables of contents (Current Issue and issue pages)
 *  - as a "Statistics" item in the details column of the article page
 * Read-only: it only reads the existing usage statistics.
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class TjArticleStatsPlugin extends GenericPlugin {

	public function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if ($success && $this->getEnabled($mainContextId)) {
			HookRegistry::register('Templates::Issue::Issue::Article', array($this, 'addStats'));
			HookRegistry::register('Templates::Article::Details', array($this, 'addArticlePageStats'));
			HookRegistry::register('Templates::Common::Sidebar', array($this, 'addMostReadBlock'));
		}
		return $success;
	}

	public function getDisplayName() {
		return 'Article Statistics in Issue Lists';
	}

	public function getDescription() {
		return 'Shows "Abstract views" and "Downloads" for each article in issue tables of contents and on the article page.';
	}

	/**
	 * Abstract views and downloads (all galleys) for an article.
	 * @return array [views, downloads]
	 */
	protected function getCounts($article) {
		$application = Application::get();
		$views = (int) $application->getPrimaryMetricByAssoc(ASSOC_TYPE_SUBMISSION, (int) $article->getId());
		$downloads = 0;
		$galleys = method_exists($article, 'getGalleys') ? $article->getGalleys() : array();
		foreach ((array) $galleys as $galley) {
			if (is_object($galley) && method_exists($galley, 'getViews')) {
				$downloads += (int) $galley->getViews();
			}
		}
		return array($views, $downloads);
	}

	/**
	 * Hook callback: statistics line below an article in an issue list.
	 */
	public function addStats($hookName, $params) {
		$smarty =& $params[1];
		$output =& $params[2];
		try {
			$article = $smarty->getTemplateVars('article');
			if (!$article || !is_object($article)) return false;
			list($views, $downloads) = $this->getCounts($article);
			$output .= '<div class="tj-stats">'
				. '<span class="tj-stats-item">Abstract views: <strong>' . number_format($views) . '</strong></span>'
				. '<span class="tj-stats-sep" aria-hidden="true">|</span>'
				. '<span class="tj-stats-item">Downloads: <strong>' . number_format($downloads) . '</strong></span>'
				. '</div>';
		} catch (Throwable $e) {
			// Never break the issue page because of statistics.
			error_log('tjArticleStats: ' . $e->getMessage());
		}
		return false;
	}

	/**
	 * Hook callback: "Statistics" item in the details column of the article page.
	 */
	public function addArticlePageStats($hookName, $params) {
		$smarty =& $params[1];
		$output =& $params[2];
		try {
			$article = $smarty->getTemplateVars('article');
			if (!$article || !is_object($article)) return false;
			list($views, $downloads) = $this->getCounts($article);
			$output .= '<div class="item tj-article-stats">'
				. '<h2 class="label">Statistics</h2>'
				. '<div class="value">'
				. '<span class="tj-stat"><strong>' . number_format($views) . '</strong> Abstract views</span>'
				. '<span class="tj-stat"><strong>' . number_format($downloads) . '</strong> Downloads</span>'
				. '</div>'
				. '</div>';
		} catch (Throwable $e) {
			error_log('tjArticleStats: ' . $e->getMessage());
		}
		return false;
	}

	/**
	 * Hook callback: "Most Read Articles" block in the sidebar
	 * (top 5 published articles by abstract views over the past year).
	 */
	public function addMostReadBlock($hookName, $params) {
		$output =& $params[2];
		try {
			$request = Application::get()->getRequest();
			$context = $request->getContext();
			if (!$context) return false;

			$articles = $this->getMostRead($context->getId());
			if (empty($articles)) return false;

			$items = '';
			foreach ($articles as $a) {
				$url = $request->url(null, 'article', 'view', $a['id']);
				$items .= '<li><a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($a['title']) . '</a>'
					. '<span class="tj-mr-views">' . number_format($a['views']) . ' views</span></li>';
			}
			$output .= '<div class="pkp_block block_tj_most_read">'
				. '<h2 class="title">Most Read Articles</h2>'
				. '<div class="content"><ol class="tj-most-read">' . $items . '</ol>'
				. '<p class="tj-mr-note">By abstract views in the last 12 months</p></div>'
				. '</div>';
		} catch (Throwable $e) {
			error_log('tjArticleStats: ' . $e->getMessage());
		}
		return false;
	}

	/**
	 * Top published articles by abstract views, cached for one day.
	 * @return array of ['id', 'title', 'views']
	 */
	protected function getMostRead($contextId) {
		$cacheManager = CacheManager::getManager();
		$cache = $cacheManager->getFileCache('tjArticleStats', 'mostRead-' . (int) $contextId, array($this, 'mostReadCacheMiss'));
		if (time() - (int) $cache->getCacheTime() > 60 * 60 * 24) {
			$cache->flush();
		}
		$list = $cache->get('list');
		return is_array($list) ? $list : array();
	}

	/**
	 * Cache miss: compute the most read list.
	 */
	public function mostReadCacheMiss($cache, $id) {
		import('classes.statistics.StatisticsHelper');
		import('lib.pkp.classes.db.DBResultRange');
		$contextId = (int) str_replace('mostRead-', '', $cache->getCacheId());

		$filter = array(
			STATISTICS_DIMENSION_CONTEXT_ID => $contextId,
			STATISTICS_DIMENSION_ASSOC_TYPE => ASSOC_TYPE_SUBMISSION,
			STATISTICS_DIMENSION_DAY => array(
				'from' => date('Ymd', strtotime('-365 days')),
				'to' => date('Ymd'),
			),
		);
		$records = Application::get()->getMetrics(
			null,
			array(STATISTICS_DIMENSION_SUBMISSION_ID),
			$filter,
			array(STATISTICS_METRIC => STATISTICS_ORDER_DESC),
			new DBResultRange(15)
		);

		$list = array();
		foreach ((array) $records as $record) {
			if (count($list) >= 5) break;
			$submissionId = (int) $record[STATISTICS_DIMENSION_SUBMISSION_ID];
			$submission = Services::get('submission')->get($submissionId);
			if (!$submission || (int) $submission->getData('contextId') !== $contextId) continue;
			if ((int) $submission->getData('status') !== STATUS_PUBLISHED) continue;
			$publication = $submission->getCurrentPublication();
			if (!$publication) continue;
			$list[] = array(
				'id' => $submission->getBestId(),
				'title' => $publication->getLocalizedFullTitle(),
				'views' => (int) $record[STATISTICS_METRIC],
			);
		}
		$cache->setEntireCache(array('list' => $list));
		return $list;
	}
}
