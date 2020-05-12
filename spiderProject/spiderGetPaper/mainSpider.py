import requests
import json
from lxml import etree


class PaperSpider:
    def __init__(self, path=None):
        import urllib3
        urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)
        if not path:
            path = "user.json"
        with open(path, "r") as f:
            _conf = json.load(f)
            self._user = _conf['user']
            self._pwd = _conf['pwd']
        self.headers = {
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.88 Safari/537.36"}
        self.sess = requests.session()
        self.sess.headers = self.headers
        self.sess.verify = False
        assert self.login_school_vpn(), "Login failed"

    def login_school_vpn(self):
        req_url = "https://ids6.usst.edu.cn/authserver/login?service=http%3A%2F%2Fmy.usst.edu.cn%2F"
        login_page_source = self.sess.get(
            url=req_url)
        login_page_etree = etree.HTML(login_page_source.text)
        lt = login_page_etree.xpath('//input[@name="lt"]/@value')
        execution = login_page_etree.xpath('//input[@name="execution"]/@value')
        post_data = {
            "username": self._user,
            "password": self._pwd,
            "lt": lt,
            "dllt": "userNamePasswordLogin",
            "_eventId": "submit",
            "rmShown": "1",
            "execution": execution
        }
        return self.sess.post(url=req_url, data=post_data)


if __name__ == '__main__':
    ps = PaperSpider()
    ps.login_school_vpn()
