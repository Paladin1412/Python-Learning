from auto_tester.normal_modules.normal import NormalTester
import json


class FourPlayerTester(NormalTester):
    def __init__(self, sess_cookies: dict, json_path="interface_list.json"):
        super(FourPlayerTester, self).__init__(sess_cookies)
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

    }
    interface_list = ()
    fpt = FourPlayerTester(cookies)

    ret = fpt.get_url_json('')
    print(ret)
