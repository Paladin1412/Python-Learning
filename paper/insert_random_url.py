import random
import string
from concurrent.futures import ThreadPoolExecutor, as_completed
import time
import redis
import json
import threading
import queue
with open('../config.json', 'r') as f:
    ret = json.load(f)
    pwd = ret['redis']['pwd']
pool = redis.ConnectionPool(host="192.168.11.31", port="30002", password=pwd, db=1)
rc = redis.Redis(connection_pool=pool,decode_responses=True)
def create_html(queue):
    for i in range(25000):
        random_url = 'https://www.' + ''.join(random.sample(string.ascii_letters + string.digits, 16)) + '.html'
        queue.put(random_url)
    return "create success"
def insert_redis(queue):
    time.sleep(3)
    while not queue.empty():
        url = queue.get()
        rc.set(url,0)
    return "insert redis success"


if __name__ == '__main__':
    start_time = time.time()
    q = queue.Queue()
    executor = ThreadPoolExecutor(max_workers=64)
    at=[executor.submit(create_html,(q)) for i in range(40)]+[executor.submit(insert_redis,(q))for i in range(64)]
    # create_thread = threading.Thread(target=create_html,args=(q,))
    # create_thread.start()
    # insert_thread = threading.Thread(target=insert_redis,args=(q,))
    # insert_thread.start()
    for future in as_completed(at):
        # 哪个线程先完成先返回结果
        data = future.result()
        print(data)
    print("end time:{}".format(time.time() - start_time))

