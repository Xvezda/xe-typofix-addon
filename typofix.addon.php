<?php
/* Copyright (c) 2018 Xvezda <https://xvezda.com> */

if (!defined('__XE__')) exit();


if ($called_position == 'after_module_proc') {
    if (Context::get('typo_fix') == 'off') return;

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

        //echo $addon_info->client_id;
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

        // Redirect to suggestion
        $this->setRedirectUrl(getNotEncodedUrl($parameter, $result, 'typo_keyword', $keyword));
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
    $temp_output = $output;
    $keyword = htmlspecialchars(Context::get($parameter), ENT_COMPAT | ENT_HTML401, 'UTF-8', false);

    if (Context::get('typo_keyword') && $keyword) {
        $typo_keyword = htmlspecialchars(Context::get('typo_keyword'), ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
        Context::loadLang(_XE_PATH_ . 'addons/typofix/lang');

        $prefix = '<!--#Meta:modules/';
        // Find first position where module meta tags appear
        $pos = strpos($temp_output, $prefix.$this->module);
        if ($pos !== false) {
            // Inject information box into top of content
            $info_box = '<div id="typofix_info" style="line-height: 25px; padding: 15px 0; font-size: 14px">' . sprintf(Context::getLang('typofix_info_msg'), $keyword) . ' &nbsp;<a href="' . getAutoEncodedUrl($parameter, $typo_keyword, 'typo_keyword', '', 'typo_fix', 'off') . '">' . sprintf(Context::getLang('typofix_info_more_msg'), $typo_keyword) . '</a></div>';
            $result = substr_replace($temp_output, $info_box, $pos, 0);
            $output = $result;
        }
    }
}


/* End of file typofix */
/* Location: ./addons/typofix/typofix.addon.php */
