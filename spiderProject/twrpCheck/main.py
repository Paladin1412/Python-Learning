import requests
import urllib3
from lxml import etree
import redis
import time
import random
import sys
import logging

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)
iota = 1
logging.basicConfig(level=logging.INFO,
                    format='%(asctime)s %(levelname)s %(message)s', )
while True:
    logging.info("This is {} captcha.".format(iota))
    url = "https://twrp.me/Devices/Xiaomi/"
    headers = {
        "User-Agent": "Mozilla/5.0 (iPhone; CPU iPhone OS 13_3_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 NewsArticle/7.5.7.32 JsSdk/2.0 NetType/WIFI (News 7.5.7 13.300000)",
        "Accept": "application/json, text/javascript"
    }
    sess = requests.session()
    sess.headers = headers
    sess.verify = False
    ret_page = sess.get(url=url).text
    e_page = etree.HTML(ret_page)
    ret = e_page.xpath('//ul[@class="post-list"]/p/strong/a/text()')
    for i in ret:
        if "10" in i:
            rc = redis.Redis(host="192.168.11.31", port="30002")
            rc.set("twrpCheck", "True")
            logging.info("TWRP CHECK EXIST!!!")
            sys.exit(0)
    iota += 1
    time.sleep(60 * random.uniform(1, 2))
