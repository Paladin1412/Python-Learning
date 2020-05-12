import requests
from lxml import etree
import urllib3

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)
headers = {
            "User-Agent": "Mozilla/5.0 (iPhone; CPU iPhone OS 13_3_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 NewsArticle/7.5.7.32 JsSdk/2.0 NetType/WIFI (News 7.5.7 13.300000)",
            "Accept": "application/json, text/javascript"
        }
cookies={
"show_my_view1121040201001487131sort":"created%7EDESC",
"show_my_view1121040201001487132sort":"created%7EDESC",
"show_my_view1121040201001487137sort":"created%7EDESC",
"tui_filter_fields":"%5B%22title%22%2C%22iteration_id%22%2C%22priority%22%2C%22status%22%5D",
"242956525_21040201_/bugtrace/bugreports/my_view_remember_view":"1121040201001487137",
"bugtrace_reports_myview_21040201_filter_fields":"%5B%22version_report%22%2C%22module%22%5D",
"mng-go":"209925cd99f458a117223c2e9317bd5b14e932c7a15883217f449c975b0d3e75",
"tapdsession":"1581672263f34a77f52bad8b93d5f81a8889f806d9166cb9fd05e3d4d9ea57c77e1c59a1b7",
"_t_uid":"242956525",
"_t_crop":"20055901",
"tapd_div":"1_472",
"iteration_view_type_cookie":"card_view",
"username":"chenbo01",
"_AJSESSIONID":"f3075c0579f964fbedd383bc5b07d63c",
"_gitlab_session":"558fc3c514ba786ad1ba9eb25273a99e",
"_ewt":"U2FsdGVkX19XFMUWj2JFSfVLV9D6nd94cyxJupDnFRXawAkw6W78vm9T2Qo67YBfNvx8Td%2BvIbrqEjVMfhlUywBJO7OgDtXYCGgMWtYlRlc%2BTtu2b%2Fifmkge74X9O36HwmCDqBMAo4dVtXbS%2F2S5fNoj0V7ZJvbu4UmAMXHz%2FtU%3D.cc187613252a8903e1dedf1dbf4d210f8d5ec0ef5ddbaf475ff39efa89e364d4",
"new_worktable":"todo%7C21040201%7C72%7Cexpiration_date",
"dsc-token":"6odNPGuNjVOwN7cc",
"_wt":"eyJ1aWQiOiIyNDI5NTY1MjUiLCJjb21wYW55X2lkIjoiMjAwNTU5MDEiLCJleHAiOjE1ODE5MzM0NDh9.592c75e2d9898b21d24d53c8d568771698968f7c10d165c8d6b134e842f7aa3d"
}

sess = requests.session()
sess.headers=headers
sess.verify=False

pattern = '//a[@class="editable-value namecol"]/text()'


ret_page = sess.get("https://www.tapd.bilibili.co/21040201/bugtrace/bugreports/my_view?conf_id=1121040201001487137&query_token=202002172eb7e5aab90e74f50fbd0ffb0cffc15d",cookies=cookies)
resolve_page=etree.HTML(ret_page.text)
ret=resolve_page.xpath(pattern)
for i in ret:
    print(i.strip())