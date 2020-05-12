import requests

class WikiSpider:
    def __init__(self,cookies):
        self.cookies=cookies
        self.sess = requests.session()
        self.sess.headers= {
            'User-Agent': 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36',
            "Connection": "keep-alive",
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3",
            "Sec-Fetch-Mode": "navigate", "Sec-Fetch-Site": "none", "Sec-Fetch-User": "?1",
            "Upgrade-Insecure-Requests": "1", "Accept-Encoding": "gzip, deflate,br",
            "Accept-Language": "zh-CN,zh;q=0.9", "Cache-Control": "max-age=0",
            }

    def test(self):
        ret = self.sess.get("http://wiki.baidu.com/pages/viewpage.action?pageId=218302795",cookies=self.cookies)
        print(ret.text)
if __name__ == '__main__':
    cookies=""
    ws = WikiSpider(cookies)
    ws.test()