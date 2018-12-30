<?php
/* Copyright (c) 2018 Xvezda <https://xvezda.com> */

if (!defined('__XE__')) exit();

if ($called_position == 'after_module_proc') {
    if ($this->module == 'addon' && $this->act == 'dispAddonAdminSetup'
	    && Context::get('selected_addon') == 'typofix') {
		$logged_info = Context::get('logged_info');
        if ($logged_info->is_admin !== 'Y') return;
        if ($_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $oCacheHandler = CacheHandler::getInstance();
            if ($oCacheHandler->isSupport()) {
                // FIXME: Find a way to clear cache just for addon
                $oCacheHandler->truncate();
            }
            Context::close();
            exit(0);
        } else {
            $customize_html = <<<EOD
<script>
xe.lang.cmd_delete = '%s';
xe.lang.confirm_delete = '%s';
xe.lang.success_deleted = '%s';
xe.lang.fail_to_delete = '%s';
jQuery("#delete_cache").attr("type", "button").attr("value", xe.lang.cmd_delete)
.addClass("x_btn").addClass("x_btn-danger")
.on("click", function(e) {
    if (!confirm(xe.lang.confirm_delete)) return;
    jQuery.ajax({
        url: current_url,
        type: 'post',
        dataType: 'json',
        success: function(data) {
            alert(xe.lang.success_deleted);
        },
        error: function(err) {
            alert(xe.lang.fail_to_delete);
        }
    });
    e.preventDefault();
});
</script>
EOD;
            Context::addHtmlFooter(
                sprintf($customize_html, Context::getLang('cmd_delete'),
                                         Context::getLang('confirm_delete'),
                                         Context::getLang('success_deleted'),
                                         Context::getLang('fail_to_delete')
                )
            );
        }
	}
}

if ($called_position == 'after_module_proc') {
    if (Context::get('typo_fix') == 'off') return;
    if (!$addon_info->board_enable && $this->module == 'board') return;

    if ($this->act == 'IS' && Context::get('is_keyword')
        || $this->act == 'dispBoardContent' && Context::get('search_keyword')) {
        switch ($this->module) {
        case 'board':
            $parameter = 'search_keyword';
            break;
        case 'integration_search':
            $parameter = 'is_keyword';
            break;
        default:
            return;
        }
        $keyword = Context::get($parameter);

        if (!$addon_info->client_id || !$addon_info->client_secret) return;

        $result = '';
        $oCacheHandler = CacheHandler::getInstance();
        if ($oCacheHandler->isSupport()) {
            $key = 'typofix:keyword:'. md5($keyword);
            $result = $oCacheHandler->get($key);
        }
        if (!$result) {
            $api_url = 'https://openapi.naver.com/v1/search/errata.xml';
            $api_url .= '?query=' . $keyword;

            $api_header = array();
            $api_header['Host'] = 'openapi.naver.com';
            $api_header['Pragma'] = 'no-cache';
            $api_header['Accept'] = '*/*';
            // Set naver open api requirements
            $api_header['X-Naver-Client-Id'] = $addon_info->client_id;
            $api_header['X-Naver-Client-Secret'] = $addon_info->client_secret;

            $request_config = array();
            $request_config['ssl_verify_peer'] = false;

            $buff = FileHandler::getRemoteResource($api_url, null, 3, 'GET', null,
                    $api_header, array(), array(), $request_config);

            $xml = new XmlParser();
            $xmlDoc = $xml->parse($buff);

            if (!$buff) return;

            $result = $xmlDoc->result->item->errata->body;

            if (!$result) return;
            if ($oCacheHandler->isSupport()) {
                $key = 'typofix:keyword:'. md5($keyword);
                $oCacheHandler->put($key, $result);
            }
        }
        if ($addon_info->force_correction) {
            // Redirect to suggestion
            $this->setRedirectUrl(getNotEncodedUrl($parameter, $result, 'typo_keyword', $keyword));
        } else {
            Context::set('suggest_keyword', $result);
        }
    } else if (Context::get('typo_keyword')) {
        $this->setRedirectUrl(getNotEncodedUrl('typo_keyword', ''));
    }
}


if ($called_position == 'before_display_content') {
    if (Context::get('act') == 'IS') {
        $parameter = 'is_keyword';
    } else if (Context::get('search_target')) {
        $parameter = 'search_keyword';
    } else {
        return;
    }
    Context::loadFile('./addons/typofix/css/style.css');

    $temp_output = $output;
    $keyword = htmlspecialchars(Context::get($parameter), ENT_COMPAT | ENT_HTML401, 'UTF-8', false);

    if ( (!$addon_info->force_correction && Context::get('suggest_keyword')
          || $addon_info->force_correction && Context::get('typo_keyword'))
                && $keyword) {
        Context::loadLang(_XE_PATH_ . 'addons/typofix/lang');

        $prefix = '<!--#Meta:modules/';
        // Find first position where module meta tags appear
        $pos = strpos($temp_output, $prefix.$this->module);
        if ($pos === false) return;

        // Inject information box into top of content
        $info_box = '';
        $info_box_prefix = '<div id="typofix_info">';
        $info_box_suffix = '</div>';

        if ($addon_info->force_correction) {
            $typo_keyword = htmlspecialchars(Context::get('typo_keyword'), ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
            $info_box = $info_box_prefix
                . sprintf(Context::getLang('typofix_info_msg'), $keyword)
                . ' &nbsp;<a href="'
                . getAutoEncodedUrl($parameter, urlencode($typo_keyword), 'typo_keyword', '', 'typo_fix', 'off')
                . '">'
                . sprintf(Context::getLang('typofix_info_more_msg'),
                          $typo_keyword)
                . '</a>'
                .$info_box_suffix;
        } else {
            $suggest_keyword = htmlspecialchars(Context::get('suggest_keyword'), ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
            $info_box = $info_box_prefix
                . sprintf(Context::getLang('typofix_info_msg'), $keyword)
                . ' &nbsp;<a href="'
                . getAutoEncodedUrl($parameter, urlencode($suggest_keyword))
                . '">'
                . sprintf(Context::getLang('typofix_info_suggest_msg'),
                          $suggest_keyword)
                . '</a>'
                . $info_box_suffix;
        }
        $result = substr_replace($temp_output, $info_box, $pos, 0);
        $output = $result;
    }
}


/* End of file typofix */
/* Location: ./addons/typofix/typofix.addon.php */
