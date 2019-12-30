import requests
import time
import datetime

class my_dash:
    def __init__(self,token):
        self.token = token
        self.headers={'User-Agent':'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36',
        "Connection":"keep-alive","Accept":"text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3",
        "Sec-Fetch-Mode": "navigate","Sec-Fetch-Site": "none","Sec-Fetch-User":"?1","Upgrade-Insecure-Requests": "1","Accept-Encoding":"gzip, deflate,br",
        "Accept-Language": "zh-CN,zh;q=0.9","Cache-Control": "max-age=0",
        }
        self.sess = self.login()
    def login(self):
        t = time.time()
        ts = lambda:int(round(t * 1000))
        url=""
        sess = requests.session()
        data={
            "username":"",
            "password":"",
            "token":self.token,
            "caller":"",
            "path":"",
            "ts":ts,
            "encode":"false"    
        }
        sess.post(url=url,data=data)
        return sess
    
    def get_doc(self):
        ret = self.sess.get("",headers=self.headers)
        ret.encoding='utf-8'
        print(ret.text)
       
    
if __name__ == "__main__":
    md = my_dash("752488")
    md.get_doc()    