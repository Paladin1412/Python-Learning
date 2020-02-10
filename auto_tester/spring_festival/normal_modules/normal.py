import requests
import json
import redis
import pymongo
from queue import Queue


class NormalTester:
    def __init__(self, cookies):
        import urllib3
        urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)
        # with open("./config.json", "r") as fp:
        #     ret = json.load(fp)
        #     host = ret['host']
        #     redis_pwd = ret['redis']['pwd']
        #     redis_port = ret['redis']['port']
        #     mongo_user = ret['mongo']['user']
        #     mongo_pwd = ret['mongo']['pwd']
        #     mongo_port = ret['mongo']['port']
        self.sess = requests.session()
        self.sess.headers = {
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) "
                          "Chrome/78.0.3904.108 Safari/537.36 "
        }
        self.cookies = cookies
        self.sess.verify = False
        # self.redis_cli = redis.Redis(host=host, port=redis_port, password=redis_pwd, db=1)
        # self.mongo_cli = pymongo.MongoClient(host=host, port=mongo_port, username=mongo_user, password=mongo_pwd, authSource='admin')
        self.queue = Queue(maxsize=1000)

    def get_url(self, url):
        return self.sess.get(url, cookies=self.cookies)

    def post_url(self, url, data):
        return self.sess.post(url=url, data=data)

    def get_url_json(self, url):
        return json.loads(self.get_url(url).text)

    def post_url_json(self, url, data):
        return json.loads(self.sess.post(url=url, data=data).text)
