import requests
import json


class my_interface_test:
    def __init__(self, url=None, headers=None, count=1, data=None, get_param=None):
        domain = ""
        self.headers = headers
        self.url = domain + url
        self.count = count
        self.data = data
        self.get_param = get_param

    @staticmethod
    def create_post_data(**kwargs):
        return kwargs

    @staticmethod
    def create_get_url_param(*args):
        param = ""
        for i in args:
            param += i + "&"
        return param[0:-1]

    @property
    def test_url(self):
        return self.url

    def get_url(self):
        if self.get_param:
            self.url = self.url + "?" + self.get_param
            rep = requests.get(headers=self.headers, url=self.url)
        else:
            rep = requests.get(headers=self.headers, url=self.url)
        return {"code": rep.status_code, "ret": rep.text}

    def post_url(self):
        rep = requests.post(headers=self.headers, url=self.url, data=self.data)
        return {"code": rep.status_code, "ret": rep.text}

    def unique_method(self):
        return None
