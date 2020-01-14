from auto_tester.normal_modules.normal import NormalTester
import json
import urllib3

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)


class FourPlayerTester(NormalTester):
    def __init__(self, sess_cookies=None, json_path="interface_list.json"):
        super(FourPlayerTester, self).__init__(cookies=sess_cookies)
        self.json_path = json_path

    def get_task(self):
        with open(self.json_path, "r", encoding='utf-8')as fp:
            task_json = json.load(fp)
        return task_json

    def case(self, data: dict = None):
        tasks = self.get_task()
        for task in tasks:
            url = task['url']
            method = task['method']
            if method == 'GET':
                self.get_url(url)
            else:
                self.post_url(url=url, data=data)

        # ret = self.get_url("")
        # print(ret.text)


if __name__ == '__main__':
    cookies = {
        "_uuid": "98D4AB93-B182-A1BA-AF7B-5B461E863F2F95141infoc",
        "bili_jct": "9203c173145c8df417d263511f2d2bc2",
        "buvid3": "6B7B83D1-3BC4-4AE8-8447-BF225E41F760155827infoc",
        "DedeUserID": "15555180",
        "DedeUserID__ckMd5": "c958b2601f1ca25e",
        "LIVE_BUVID": "AUTO9015783875853651",
        "SESSDATA": "4e0a3788,1581047202,2aa2dc11",
        "sid": "hszjkz2e"
    }

    fpt = FourPlayerTester(cookies)
    fpt.get_url("https://www.bilibili.com/blackboard/preview/chunjie-m.html")
    # 获取奖励
    for i in range(10):
        try:
            data = {
                'task_id': 12648,
                'csrf': '9203c173145c8df417d263511f2d2bc2'
            }
            url = 'https://uat-api.bilibili.com/x/activity/task/award'
            ret = fpt.post_url_json(url=url, data=data)
            print("获取奖励接口信息：", ret)
        except Exception as e:
            print(e)
    # 获取任务列表
    get_task_url = 'http://api.bilibili.com/x/activity/task/list?sid=10671'
    gtu = fpt.get_url_json(url=get_task_url)
    print("获取任务列表信息接口信息：", gtu)
    # 99.批量获取活动信息
    url_99 = 'http://api.bilibili.com/x/activity/subjects?sids=10671'
    ret_99 = fpt.get_url_json(url_99)
    print("批量获取活动信息接口信息：", ret_99)
