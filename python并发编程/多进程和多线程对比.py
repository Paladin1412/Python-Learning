#耗cpu的操作，用多进程编程，对于io操作来说，使用多线程编程，因为进程切换代价高于线程

#1.对于耗费cpu的操作
import time
def fib(n):
    if n<=2:
        return 1
    return fib(n-1)+fib(n-2)
#使用多线程
from concurrent.futures import ThreadPoolExecutor,as_completed
if __name__ == '__main__':
    with ThreadPoolExecutor(3) as executor:
        all_task = [executor.submit(fib,(num))for num in range(25,40)]
        start_time = time.time()
        for future in as_completed(all_task):
            data=future.result()
            print("get {}".format(data))
        print("Use thread end time is:{}".format(time.time()-start_time))
    #使用多进程
    del ThreadPoolExecutor
    from concurrent.futures import ProcessPoolExecutor
    with ProcessPoolExecutor(3) as executor:
        all_task = [executor.submit(fib,(num))for num in range(25,40)]
        start_time = time.time()
        for future in as_completed(all_task):
            data=future.result()
            print("get {}".format(data))
        print("Use process end time is:{}".format(time.time()-start_time))