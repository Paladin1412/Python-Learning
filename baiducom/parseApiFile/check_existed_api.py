# -*- coding:utf-8 -*-
import os
from concurrent.futures import ThreadPoolExecutor, as_completed
import re
import requests
from threading import Thread
import json
import time


class ParseApiPath:
    def __init__(self):
        self.target_file_list = []
        self.api_list = []
        self.success_file = []
        self.params = {"userName": "zhouzhihui01", "productId": 7400}
        self.sess = requests.session()
        self.sess.verify = False
        self.existed_api = []

    def get_all_children_suite(self) -> dict:
        url = "http://icase.baidu.com/ws/testCase/casetree/24245530?"  # 获取所有服务端cases
        headers = {"Accept-Encoding": "br"}
        ret = self.sess.get(url, params=self.params, headers=headers)
        if ret.status_code == 200:
            ret = json.loads(ret.text)
            children_suite = ret['retSingleData']['children']
            # print(children_suite)
            return children_suite
        # return {}

    def add_existed(self, target):
        if 'bduss' in target['expect']:
            # url = "http://icase.baidu.com/ws/testCase/{}".format(target['id'])
            # ret = self.sess.delete(url, params=self.params)
            print(target['title'])
        # title = target['title'].replace("/", "").replace("_", "")
        # if title == 'v5activitylist':
        #     print(target['precondition'])
        # if title in self.existed_api:
        #     print(target['title'])
        # else:
        #     self.existed_api.append(title)

    def get_all_existed_cases(self):
        all_suite = self.get_all_children_suite()
        if isinstance(all_suite, list):
            for suite in all_suite:
                this_suite_children = suite['children']
                this_suite_case = suite['cases']
                if this_suite_case:
                    for i in this_suite_case:
                        self.add_existed(i)

                for children in this_suite_children:
                    children_cases = children['cases']
                    for case in children_cases:
                        self.add_existed(case)

    def main_process(self):
        self.get_all_existed_cases()


if __name__ == '__main__':
    pap = ParseApiPath()
    pap.main_process()
