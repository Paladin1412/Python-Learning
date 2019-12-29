from concurrent.futures import ThreadPoolExecutor,as_completed
import time

def get_html(times):
    time.sleep(times)
    print("get page {} success".format(times))
    return times
executor = ThreadPoolExecutor(max_workers=2)
# t1 = executor.submit(get_html,(3))
# t2 = executor.submit(get_html,(2))
# print(t1.done())#判定任务是否完成
# print(t2.cancel())
# time.sleep(4)
# print(t1.done())
# print(t1.result())#获取执行结果
urls = [3,2,4]
all_task = [executor.submit(get_html,(i)) for i in urls]
for future in as_completed(all_task):
    #哪个线程先完成先返回结果
    data=future.result()
    print("get {} page success".format(data))
for data in executor.map(get_html,urls):
    #根据all_task顺序来返回结果
    print("get {} page success".format(data))

