import requests
import json
from .api_lists import choose_api


class myInterfaceTest:
    def __init__(self, api_part, domain, url=None, headers=None, count=1, param=None):
        api = choose_api(domain, api_part)
        assert api, "Can't find this api"
        self.api = api
        self.headers = headers
        self.count = count
        self.param = param
        self.url = domain + api['apipart']
        print(self.url)

    def create_get_url_param(self):
        param = ""
        for i in self.param:
            param += i + "&"
        return param[0:-1]

    @property
    def test_url(self):
        return self.url

    def get_url(self):
        if self.param:
            self.get_param = self.create_get_url_param()
            self.url = self.url + "?" + self.get_param
            rep = requests.get(headers=self.headers, url=self.url)
        else:
            rep = requests.get(headers=self.headers, url=self.url)
        print("The request url is:", self.url)
        return rep.text

    def post_url(self):
        rep = requests.post(headers=self.headers, url=self.url, data=self.param)
        return rep.text

    def handle_process(self):
        method = self.api["method"]
        if method == "get":
            ret = self.get_url()
        elif method == "post":
            assert self.param, "You must define data first!"
            ret = self.post_url()
        self.store_file(ret)

    def store_file(self, string):
        with open("config.json", "w", encoding="utf-8") as fp:
            fp.writelines(str(string))


if __name__ == "__main__":
    param = ["build=1221", "tv=11"]
    mt = myInterfaceTest(api_part="modpage", domain="http://api.bilibili.com", param=param)
    mt.handle_process()
