import requests
import json
import time
import datetime
while True:
    url="http://api.bilibili.com/x/tv/modpage_v2?appkey=4409e2ce8ffd12b8&build=102300&channel=master&mobi_app=android_tv_yst&page_id=21&platform=android&ts=1579230623&sign=712053d21b83e637139484835e905d0e"
    ret = requests.get(url)
    rejson=json.loads(ret.text)
    print(datetime.datetime.now())
    print(rejson['data'][len((rejson)['data']) - 1])
    if '智能数码' not in rejson['data'][len((rejson)['data'])-1]['name']:
        break
    time.sleep(2)