import requests
import json
import random
import time
import threading
import string
import redis
import queue
import math
class CmsTest:
    def __init__(self, user=None, pwd=None):
        self.user = user
        self.pwd = pwd
        self.sess = requests.session()
        self.headers = {
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36"
        }
        self.redis = redis.Redis(host="michael.qicp.net", port="50000", password="cccbbb",db=1)
        self.queue = queue.Queue(maxsize=1000)
    @property
    def redisCli(self):
        return self.redis
    @redisCli.setter
    def redisCli(self,value):
        self.redis = redis.Redis(value)

    def get_code(self):
        random_num = "".join(str(random.choice(range(10))) for _ in range(17))
        code_url = "http://uat-tv-cp.bilibili.co/api/v4/captcha?t=0." + random_num
        code_ret = self.sess.get(url=code_url, headers=self.headers)
        now_time_png = str(time.strftime('%H-%M-%S', time.localtime())) + ".png"
        with open(now_time_png, "wb") as f:
            f.write(code_ret.content)

    def login_manager(self):
        cookies = {
            "mng-bilibili": "3oudved3muimfbsju06tgi9a80",
            "username": "chenbo01",
            "uid": "1483",
            "_AJSESSIONID": "9cbd7fa77f148f3d7e2d7d09cb2bfbe4",
            "_gitlab_session": "4c5f369b6bede1ea6b1777c601e28237",
            "mng-go": "7086abc68d65665fbc7b06cb1b1703f837cf3a7447211c63ba829b2b92c4f0c4"
        }
        return self.sess.get(url="http://uat-manager.bilibili.co/v2/#/home", cookies=cookies, headers=self.headers)

    def add_account(self):
        self.login_manager()
        for i in range(10):
            account = ''.join(random.sample(string.ascii_letters + string.digits, 10))
            addAccountData = {
                "name": "autoScript" + str(i + 1),
                "account": account,
                "password": account
            }
            addAccountRep = self.sess.post(headers=self.headers,
                                           url="http://uat-manager.bilibili.co/x/admin/tv/account/add",
                                           data=addAccountData)

            ret = json.loads(addAccountRep.text)
            print(ret)

    def del_account(self):
        self.login_manager()
        url = "http://uat-manager.bilibili.co/x/admin/tv/account/list"
        ret = self.sess.get(url,headers=self.headers)
        retJson = json.loads(ret.text)
        page_num = math.ceil(retJson["data"]["page"]["total"]/20)
        for i in range(page_num):
            targetUrl = url+"?pn="+page_num
            page_num -= i



    def login_cms(self):
        login_url = "http://uat-tv-cp.bilibili.co/api/v4/login"
        code = input("Please enter the code: ")
        data = {
            "username": self.pwd,
            "password": self.user,
            "verify": code
        }
        print(data)
        rep = self.sess.post(url=login_url, headers=self.headers, data=data)
        ret = json.loads(rep.text)
        print(ret)
        mainPage = self.sess.get(url="http://uat-tv-cp.bilibili.co/#/content-lib/list", headers=self.headers)
        mainPage.encoding = 'utf-8'
        print(mainPage.text)


if __name__ == '__main__':
    # userList = [{"user": "M", "pwd": "ss"}]
    # for i in userList:
    #     ct = CmsTest(user=i['user'], pwd=i['pwd'])
    #     ct.login_manager()
    ct = CmsTest()
    ct.del_account()

