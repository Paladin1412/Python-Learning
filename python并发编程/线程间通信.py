# 线程间通信
# 1.线程通信方式-共享变量
import time
import threading
from queue import Queue
# detail_url_list = []


# def get_detail_html():
#     while True:
#         if len(detail_url_list):
#             url = detail_url_list.pop()
#             print("get html start")
#             time.sleep(2)
#             print(("get html end"))


# def get_detail_url():
#     print("get url start")
#     time.sleep(4)
#     for i in range(20):
#         detail_url_list.append("http://www.baidu.com/{id}".format(id=i))
#     print("get url end")


# if __name__ == "__main__":
#     thread_detail_url = threading.Thread(target=get_detail_url)
#     thread_detail_url.start()
#     for i in range(10):
#         html_thread = threading.Thread(target=get_detail_html)
#         html_thread.start()
#     start_time = time.time()
#     thread_detail_url.join()
#     html_thread.join()
#     print("end time:{}".format(time.time()-start_time))

#2.通过队列方式进行线程间同步
def get_detail_html(queue):
    while True:
        url = queue.get()
        print("get html start")
        time.sleep(2)
        print(("get html end")) 

def get_detail_url(queue):
    print("get url start")
    time.sleep(4)
    for i in range(20):
        queue.put("http://www.baidu.com/{id}".format(id=i))
    print("get url end")
if __name__ == "__main__":
    detail_url_queue = Queue(maxsize=1000)
    thread_detail_url = threading.Thread(target=get_detail_url,args=detail_url_queue)
    for i in range(10):
        html_thread = threading.Thread(target=get_detail_html,args=detail_url_queue)
        html_thread.start()
    start_time = time.time()
    print("end time:{}".format(time.time()-start_time))