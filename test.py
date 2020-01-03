import requests
import threading
from concurrent.futures import ThreadPoolExecutor,as_completed
def get_url():
    for i in range(100):
        ret = requests.get('http://192.168.11.31',headers={'Host':'ly.michael.home'})
        if ret.status_code!=200:
            print(ret.text)
    return "Success!"

if __name__ == '__main__':
    executor = ThreadPoolExecutor(max_workers=8)
    at=executor.submit(get_url())
    ret = as_completed(at)
    print(ret)