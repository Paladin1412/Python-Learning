# 对于io操作来说，多线程和多进程性能差别不大
import time
import threading


# def get_detail_html(url):
#     print("get html start")
#     time.sleep(2)
#     print(("get html end"))

# def get_detail_url(url):
#     print("get url start")
#     time.sleep(2)
#     print("get url end")

# if __name__ == "__main__":
#     thread1 = threading.Thread(target=get_detail_html,args=("",))
#     thread2 = threading.Thread(target=get_detail_url,args=("",))
#     start_time = time.time()
#     thread1.start()
#     thread2.start()
#     thread1.join()#阻塞，防止main进程运行
#     thread2.join()
#     print("end time:{}".format(time.time()-start_time))

class GetDetailHtml(threading.Thread):
    def __init__(self, name):
        super().__init__(name=name)

    def run(self):
        print("get html start")
        time.sleep(2)
        print(("get html end"))


class GetDetailUrl(threading.Thread):
    def __init__(self, name):
        super().__init__(name=name)

    def run(self):
        print("get url start")
        time.sleep(2)
        print("get url end")


if __name__ == "__main__":
    thread1 = GetDetailHtml("get_html")
    thread2 = GetDetailUrl("get_url")
    start_time = time.time()
    thread1.start()
    thread2.start()
    thread1.join()  # 阻塞，防止main进程运行
    thread2.join()
    print("end time:{}".format(time.time() - start_time))
